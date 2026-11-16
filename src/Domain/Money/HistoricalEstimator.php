<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Infrastructure\DataPack\DataPackDirectory;
use App\Infrastructure\DataPack\DataPackReaderInterface;

final readonly class HistoricalEstimator
{
    private const float Z_95 = 1.96;

    private const array NOTES = [
        'Observational data: called vs left alone is an association, not a causal effect.',
        'Called means the first call activity carrying the sold_out_id is on or before restocked_date (any date'
            .' if never restocked): a call after the restock cannot have caused it. Other activity types are ignored.',
        'Replayed activities (same merchant, sold-out, date, type, owner and duration) only feed the duplicate'
            .' count: called is a set of sold-outs, so a replay cannot change it.',
        'The Deal Alert rule does not read alert_type, so in effect it means the automated sold_out alert fired;'
            .' it drops about 64% of sold-outs from the unconfounded cut.',
        'Sold-outs and merchant-months of one merchant are treated as independent, so intervals are too narrow.',
        'Blank gp_30d_after_gbp rows are left out of the GP means but kept in the restock rates.',
        'Recent sold-outs may not have restocked yet, which lowers their restock rate.',
        'Intervals are estimate +/- 1.96 standard errors; rates are clipped to [0, 1].',
        'Refund rate is refunds over GP plus refunds across all of the band\'s merchant-months.',
        'Unconfounded drops sold-outs with an active schedule or a Deal Alert send in the 7 days before.',
        'Not handled yet: blank rep_id, non-rep owner_id, late refunds, CRM migration rows, alerts before Aug 2025.',
    ];

    public function __construct(
        private DataPackReaderInterface $reader,
    ) {
    }

    public function estimate(DataPackDirectory $directory): HistoricalEstimates
    {
        $records = $this->reader->read($directory);

        $bandByMerchant = [];
        foreach ($records->merchants as $merchant) {
            $bandByMerchant[$merchant->merchantId] = $merchant->sizeBand;
        }

        [$firstCalls, $duplicates] = $this->firstCalls($records->activities);
        $schedulesByDeal = $this->groupByDeal($records->schedules ?? []);
        $sendsByDeal = $this->groupByDeal($records->dealAlertSends ?? []);

        $soldOutsByBand = array_fill_keys(
            array_map(static fn (SizeBand $b): string => $b->value, SizeBand::cases()),
            [],
        );
        $unconfoundedByBand = $soldOutsByBand;
        $withoutBand = 0;
        $withSchedule = 0;
        $withDealAlert = 0;
        foreach ($records->soldOuts as $soldOut) {
            $band = $bandByMerchant[$soldOut->merchantId] ?? null;
            if ($band === null) {
                ++$withoutBand;
                continue;
            }

            $scheduled = $this->anyScheduleActive($schedulesByDeal[$soldOut->dealId] ?? [], $soldOut->soldOutDate);
            $alerted = $this->anySendPrecedes($sendsByDeal[$soldOut->dealId] ?? [], $soldOut->soldOutDate);
            $withSchedule += $scheduled ? 1 : 0;
            $withDealAlert += $alerted ? 1 : 0;

            $soldOutsByBand[$band->value][] = $soldOut;
            if (!$scheduled && !$alerted) {
                $unconfoundedByBand[$band->value][] = $soldOut;
            }
        }

        $monthsByBand = array_fill_keys(array_keys($soldOutsByBand), []);
        $merchantMonths = 0;
        foreach ($records->merchantMonths as $month) {
            $band = $bandByMerchant[$month->merchantId] ?? null;
            if ($band !== null) {
                $monthsByBand[$band->value][] = $month;
                ++$merchantMonths;
            }
        }

        $bands = [];
        foreach (SizeBand::cases() as $band) {
            $bands[] = new BandEstimates(
                $band,
                $this->cut($soldOutsByBand[$band->value], $firstCalls),
                $this->cut($unconfoundedByBand[$band->value], $firstCalls),
                $this->refundRate($monthsByBand[$band->value]),
            );
        }

        $excludes = [];
        if ($records->schedules !== null) {
            $excludes[] = Confounder::AutoReplenishSchedule;
        }
        if ($records->dealAlertSends !== null) {
            $excludes[] = Confounder::DealAlertSend;
        }

        return new HistoricalEstimates(
            $bands,
            $excludes,
            \count($records->activities),
            $duplicates,
            \count($records->soldOuts),
            $withoutBand,
            $withSchedule,
            $withDealAlert,
            $merchantMonths,
            self::NOTES,
        );
    }

    /**
     * @param list<ActivityRecord> $activities
     *
     * @return array{array<string, \DateTimeImmutable>, int} the first call date per sold-out id, and the replays
     */
    private function firstCalls(array $activities): array
    {
        $seen = [];
        $firstCalls = [];
        $duplicates = 0;
        foreach ($activities as $activity) {
            $key = $activity->replayKey();
            if (isset($seen[$key])) {
                ++$duplicates;
                continue;
            }
            $seen[$key] = true;
            if ($activity->type !== ActivityType::Call || $activity->soldOutId === null) {
                continue;
            }
            $first = $firstCalls[$activity->soldOutId] ?? null;
            if ($first === null || $activity->activityDate < $first) {
                $firstCalls[$activity->soldOutId] = $activity->activityDate;
            }
        }

        return [$firstCalls, $duplicates];
    }

    /**
     * @template T of ScheduleRecord|DealAlertSendRecord
     *
     * @param list<T> $records
     *
     * @return array<string, list<T>>
     */
    private function groupByDeal(array $records): array
    {
        $byDeal = [];
        foreach ($records as $record) {
            $byDeal[$record->dealId][] = $record;
        }

        return $byDeal;
    }

    /**
     * @param list<ScheduleRecord> $schedules
     */
    private function anyScheduleActive(array $schedules, \DateTimeImmutable $date): bool
    {
        return array_any($schedules, static fn (ScheduleRecord $s): bool => $s->isActiveOn($date));
    }

    /**
     * @param list<DealAlertSendRecord> $sends
     */
    private function anySendPrecedes(array $sends, \DateTimeImmutable $date): bool
    {
        return array_any($sends, static fn (DealAlertSendRecord $s): bool => $s->precedes($date));
    }

    /**
     * @param list<SoldOutRecord>               $soldOuts
     * @param array<string, \DateTimeImmutable> $firstCalls
     */
    private function cut(array $soldOuts, array $firstCalls): CutEstimates
    {
        $called = [];
        $leftAlone = [];
        foreach ($soldOuts as $soldOut) {
            $firstCall = $firstCalls[$soldOut->soldOutId] ?? null;
            if ($firstCall !== null && $soldOut->couldBeCausedByCallOn($firstCall)) {
                $called[] = $soldOut;
            } else {
                $leftAlone[] = $soldOut;
            }
        }

        $calledGp = $this->gpPence($called);
        $leftAloneGp = $this->gpPence($leftAlone);

        return new CutEstimates(
            $this->outcome($called, $calledGp),
            $this->outcome($leftAlone, $leftAloneGp),
            $this->difference($calledGp, $leftAloneGp),
        );
    }

    /**
     * @param list<SoldOutRecord> $soldOuts
     *
     * @return list<int>
     */
    private function gpPence(array $soldOuts): array
    {
        $pence = [];
        foreach ($soldOuts as $soldOut) {
            if ($soldOut->gp30dAfter !== null) {
                $pence[] = $soldOut->gp30dAfter->toPence();
            }
        }

        return $pence;
    }

    /**
     * @param list<SoldOutRecord> $soldOuts
     * @param list<int>           $gpPence
     */
    private function outcome(array $soldOuts, array $gpPence): OutcomeEstimates
    {
        $restocked = \count(array_filter($soldOuts, static fn (SoldOutRecord $s): bool => $s->isRestocked()));

        return new OutcomeEstimates(
            \count($soldOuts),
            $this->proportion($restocked, \count($soldOuts)),
            \count($gpPence),
            $this->mean($gpPence),
        );
    }

    private function proportion(int $successes, int $n): RateEstimate
    {
        if ($n === 0) {
            return new RateEstimate(0, null, null, null, null);
        }

        $p = $successes / $n;
        $se = sqrt($p * (1 - $p) / $n);

        return new RateEstimate(
            $n,
            Ratio::fromFraction($successes, $n),
            Ratio::fromFloat($se),
            Ratio::fromFloat($this->clip($p - self::Z_95 * $se)),
            Ratio::fromFloat($this->clip($p + self::Z_95 * $se)),
        );
    }

    /**
     * @param list<int> $pence
     */
    private function mean(array $pence): MoneyEstimate
    {
        $n = \count($pence);
        if ($n === 0) {
            return new MoneyEstimate(null, null, null, null);
        }

        $central = Money::fromPence(RoundingMode::HalfAwayFromZero->divide(array_sum($pence), $n));
        if ($n < 2) {
            return new MoneyEstimate($central, null, null, null);
        }

        $mean = array_sum($pence) / $n;
        $se = sqrt($this->variance($pence, $mean) / $n);

        return new MoneyEstimate(
            $central,
            $this->pence($se),
            $this->pence($mean - self::Z_95 * $se),
            $this->pence($mean + self::Z_95 * $se),
        );
    }

    /**
     * @param list<int> $called
     * @param list<int> $leftAlone
     */
    private function difference(array $called, array $leftAlone): MoneyEstimate
    {
        $calledMean = $this->mean($called)->central;
        $leftAloneMean = $this->mean($leftAlone)->central;
        if ($calledMean === null || $leftAloneMean === null) {
            return new MoneyEstimate(null, null, null, null);
        }

        $central = $calledMean->subtract($leftAloneMean);
        if (\count($called) < 2 || \count($leftAlone) < 2) {
            return new MoneyEstimate($central, null, null, null);
        }

        $calledAverage = array_sum($called) / \count($called);
        $leftAloneAverage = array_sum($leftAlone) / \count($leftAlone);
        $difference = $calledAverage - $leftAloneAverage;
        $se = sqrt(
            $this->variance($called, $calledAverage) / \count($called)
            + $this->variance($leftAlone, $leftAloneAverage) / \count($leftAlone),
        );

        return new MoneyEstimate(
            $central,
            $this->pence($se),
            $this->pence($difference - self::Z_95 * $se),
            $this->pence($difference + self::Z_95 * $se),
        );
    }

    /**
     * Ratio of sums with a linearised (delta-method) standard error, since merchant-months differ in size.
     *
     * @param list<MerchantMonthRecord> $months
     */
    private function refundRate(array $months): RateEstimate
    {
        $n = \count($months);
        $refunds = 0;
        $gross = 0;
        foreach ($months as $month) {
            $refunds += $month->refunds->toPence();
            $gross += $month->gp->add($month->refunds)->toPence();
        }
        if ($gross <= 0) {
            return new RateEstimate($n, null, null, null, null);
        }

        $central = Ratio::fromFraction($refunds, $gross);
        if ($n < 2) {
            return new RateEstimate($n, $central, null, null, null);
        }

        $ratio = $refunds / $gross;
        $squaredResiduals = 0.0;
        foreach ($months as $month) {
            $residual = $month->refunds->toPence() - $ratio * $month->gp->add($month->refunds)->toPence();
            $squaredResiduals += $residual ** 2;
        }
        $se = sqrt($squaredResiduals / ($n * ($n - 1))) / ($gross / $n);

        return new RateEstimate(
            $n,
            $central,
            Ratio::fromFloat($se),
            Ratio::fromFloat($this->clip($ratio - self::Z_95 * $se)),
            Ratio::fromFloat($this->clip($ratio + self::Z_95 * $se)),
        );
    }

    /**
     * @param list<int> $values
     */
    private function variance(array $values, float $mean): float
    {
        $squares = 0.0;
        foreach ($values as $value) {
            $squares += ($value - $mean) ** 2;
        }

        return $squares / (\count($values) - 1);
    }

    private function clip(float $rate): float
    {
        return max(0.0, min(1.0, $rate));
    }

    private function pence(float $pence): Money
    {
        return Money::fromPence((int) round($pence));
    }
}
