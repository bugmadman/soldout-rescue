<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

use App\Domain\Money\Money;
use App\Domain\Money\RoundingMode;
use App\Infrastructure\Sandbox\SandboxClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SoldOutService
{
    private const int MINUTES_PER_HOUR = 60;

    private Money $repTimeCost;

    public function __construct(
        private SandboxClientInterface $sandbox,
        private CallGainEstimates $callGains,
        #[Autowire(param: 'app.rep_minutes_per_task')]
        int $repMinutesPerTask,
        #[Autowire(param: 'app.rep_rate_per_hour_pence')]
        int $repRatePerHourPence,
    ) {
        if ($repMinutesPerTask < 0 || $repRatePerHourPence < 0) {
            $message = \sprintf(
                'Rep time must not be negative, got %d minutes per task at %d pence per hour.',
                $repMinutesPerTask,
                $repRatePerHourPence,
            );
            throw new \DomainException($message);
        }

        $ratePerHourTimesMinutes = Money::fromPence($repRatePerHourPence)
            ->multiplyBy((string) $repMinutesPerTask, RoundingMode::HalfAwayFromZero);
        $this->repTimeCost = Money::fromPence(
            RoundingMode::HalfAwayFromZero->divide($ratePerHourTimesMinutes->toPence(), self::MINUTES_PER_HOUR),
        );
    }

    public function recommendationsOn(\DateTimeImmutable $date): RecommendedSoldOuts
    {
        $recommended = [];
        foreach ($this->sandbox->soldOutsOn($date) as $case) {
            $recommended[] = $this->recommend($case);
        }

        return new RecommendedSoldOuts(...$recommended);
    }

    public function latestSoldOutDate(): ?\DateTimeImmutable
    {
        return $this->sandbox->latestSoldOutDate();
    }

    private function recommend(SoldOutCase $case): RecommendedSoldOut
    {
        if ($case->doNotContact) {
            return $this->dontCallWithoutEstimate($case, 'The merchant is marked do not contact.');
        }

        $band = $case->merchant->sizeBand;
        $gain = $this->callGains->forBand($band);
        if ($gain?->low === null || $gain->central === null || $gain->high === null) {
            return $this->dontCallWithoutEstimate(
                $case,
                \sprintf('No historical estimate of the GP gain of a call for size band %s.', $band->value),
            );
        }

        $netLow = $gain->low->subtract($this->repTimeCost);
        $netCentral = $gain->central->subtract($this->repTimeCost);
        $netHigh = $gain->high->subtract($this->repTimeCost);
        $versusCost = $netCentral->compareTo(Money::zero());

        $reason = \sprintf(
            'Expected GP gain of a call %s the cost of rep time: %s (range %s to %s) against %s, net %s. '
            .'Observational estimate for size band %s, not a causal effect.',
            match (true) {
                $versusCost > 0 => 'exceeds',
                $versusCost === 0 => 'equals',
                default => 'is below',
            },
            $gain->central->format(),
            $gain->low->format(),
            $gain->high->format(),
            $this->repTimeCost->format(),
            $netCentral->format(),
            $band->value,
        );

        return new RecommendedSoldOut(
            $case,
            $versusCost > 0 ? Recommendation::Call : Recommendation::DontCall,
            $reason,
            $netLow,
            $netCentral,
            $netHigh,
        );
    }

    private function dontCallWithoutEstimate(SoldOutCase $case, string $reason): RecommendedSoldOut
    {
        return new RecommendedSoldOut(
            $case,
            Recommendation::DontCall,
            $reason,
            Money::zero(),
            Money::zero(),
            Money::zero(),
            false,
        );
    }
}
