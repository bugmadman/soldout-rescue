<?php

declare(strict_types=1);

namespace App\Tests\Domain\Money;

use App\Domain\Money\ActivityRecord;
use App\Domain\Money\ActivityType;
use App\Domain\Money\BandEstimates;
use App\Domain\Money\Confounder;
use App\Domain\Money\CutEstimates;
use App\Domain\Money\DealAlertSendRecord;
use App\Domain\Money\HistoricalEstimates;
use App\Domain\Money\HistoricalEstimator;
use App\Domain\Money\HistoricalRecords;
use App\Domain\Money\MerchantMonthRecord;
use App\Domain\Money\MerchantRecord;
use App\Domain\Money\Money;
use App\Domain\Money\MoneyEstimate;
use App\Domain\Money\OutcomeEstimates;
use App\Domain\Money\RateEstimate;
use App\Domain\Money\Ratio;
use App\Domain\Money\ScheduleRecord;
use App\Domain\Money\SizeBand;
use App\Domain\Money\SoldOutRecord;
use App\Infrastructure\DataPack\DataPackDirectory;
use App\Infrastructure\DataPack\DataPackReaderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HistoricalEstimator::class)]
#[CoversClass(HistoricalEstimates::class)]
#[CoversClass(BandEstimates::class)]
#[CoversClass(CutEstimates::class)]
#[CoversClass(OutcomeEstimates::class)]
#[CoversClass(RateEstimate::class)]
#[CoversClass(MoneyEstimate::class)]
#[CoversClass(ActivityRecord::class)]
#[CoversClass(SoldOutRecord::class)]
#[CoversClass(ScheduleRecord::class)]
#[CoversClass(DealAlertSendRecord::class)]
#[CoversClass(Ratio::class)]
final class HistoricalEstimatorTest extends TestCase
{
    private const string MERCHANT_A = 'M-A';
    private const string MERCHANT_B = 'M-B';

    public function testRatesMeansAndDifferenceForAHandComputableBand(): void
    {
        $estimates = $this->estimate(
            soldOuts: [
                $this->soldOut('S1', restocked: '2025-09-20', gpPence: 1000),
                $this->soldOut('S2', restocked: '2025-09-20', gpPence: 3000),
                $this->soldOut('S3', gpPence: null),
                $this->soldOut('S4', gpPence: null),
                $this->soldOut('S5', gpPence: 1000),
                $this->soldOut('S6', gpPence: 1000),
                $this->soldOut('S7', gpPence: 1000),
            ],
            activities: [$this->call('S1'), $this->call('S2'), $this->call('S3'), $this->call('S4')],
        );
        $all = $this->band($estimates, SizeBand::A)->all->toArray();

        self::assertSame(
            [
                'sold_outs' => 4,
                'restock_rate' => [
                    'n' => 4,
                    'central' => '0.5000',
                    'se' => '0.2500',
                    'low' => '0.0100',
                    'high' => '0.9900',
                ],
                'gp_30d_after_pence' => ['n' => 2, 'central' => 2000, 'se' => 1000, 'low' => 40, 'high' => 3960],
            ],
            $all['called'],
        );
        self::assertSame(
            [
                'sold_outs' => 3,
                'restock_rate' => [
                    'n' => 3,
                    'central' => '0.0000',
                    'se' => '0.0000',
                    'low' => '0.0000',
                    'high' => '0.0000',
                ],
                'gp_30d_after_pence' => ['n' => 3, 'central' => 1000, 'se' => 0, 'low' => 1000, 'high' => 1000],
            ],
            $all['left_alone'],
        );
        self::assertSame(
            ['central' => 1000, 'se' => 1000, 'low' => -960, 'high' => 2960],
            $all['gp_30d_after_difference_pence'],
        );
    }

    public function testIntervalIsClippedToZeroAndOne(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1', restocked: '2025-09-20'), $this->soldOut('S2')],
            activities: [$this->call('S1'), $this->call('S2')],
        );

        self::assertSame(
            ['n' => 2, 'central' => '0.5000', 'se' => '0.3536', 'low' => '0.0000', 'high' => '1.0000'],
            $this->band($estimates, SizeBand::A)->all->called->restockRate->toArray(),
        );
    }

    public function testEmptyGroupHasNoEstimates(): void
    {
        $estimates = $this->estimate(soldOuts: [$this->soldOut('S1', gpPence: 500)]);
        $all = $this->band($estimates, SizeBand::A)->all->toArray();

        self::assertSame(
            [
                'sold_outs' => 0,
                'restock_rate' => ['n' => 0, 'central' => null, 'se' => null, 'low' => null, 'high' => null],
                'gp_30d_after_pence' => ['n' => 0, 'central' => null, 'se' => null, 'low' => null, 'high' => null],
            ],
            $all['called'],
        );
        self::assertSame(
            ['central' => null, 'se' => null, 'low' => null, 'high' => null],
            $all['gp_30d_after_difference_pence'],
        );
    }

    public function testSingleGpValueHasACentralButNoInterval(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1', gpPence: 700), $this->soldOut('S2', gpPence: 200)],
            activities: [$this->call('S1')],
        );
        $all = $this->band($estimates, SizeBand::A)->all;

        self::assertSame(
            ['central' => 700, 'se' => null, 'low' => null, 'high' => null],
            $all->called->gp30dAfter->toArray(),
        );
        self::assertSame(
            ['central' => 500, 'se' => null, 'low' => null, 'high' => null],
            $all->gp30dAfterDifference->toArray(),
        );
    }

    public function testMeanPenceRoundsHalfAwayFromZero(): void
    {
        $estimates = $this->estimate(
            soldOuts: [
                $this->soldOut('S1', gpPence: 1),
                $this->soldOut('S2', gpPence: 2),
                $this->soldOut('S3', gpPence: -1),
                $this->soldOut('S4', gpPence: -2),
            ],
            activities: [$this->call('S1'), $this->call('S2')],
        );
        $all = $this->band($estimates, SizeBand::A)->all;

        self::assertSame(2, $all->called->gp30dAfter->central?->toPence());
        self::assertSame(-2, $all->leftAlone->gp30dAfter->central?->toPence());
        self::assertSame(4, $all->gp30dAfterDifference->central?->toPence());
    }

    public function testReplayedActivitiesAreDedupedAndOnlyCallsOnTheSoldOutCount(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1'), $this->soldOut('S2'), $this->soldOut('S3')],
            activities: [
                $this->call('S1'),
                $this->call('S1'),
                $this->call('S1', duration: '301'),
                $this->activity('S2', ActivityType::Email),
                $this->activity('S3', ActivityType::Note),
                $this->activity(null, ActivityType::Call),
            ],
        );

        self::assertSame(6, $estimates->activityRows);
        self::assertSame(1, $estimates->activityDuplicateRows);
        self::assertSame(1, $this->band($estimates, SizeBand::A)->all->called->soldOuts);
        self::assertSame(2, $this->band($estimates, SizeBand::A)->all->leftAlone->soldOuts);
    }

    public function testASoldOutCalledTwiceWithDifferentDurationsCountsOnce(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1', gpPence: 1000)],
            activities: [$this->call('S1', duration: '60'), $this->call('S1', duration: '600')],
        );
        $called = $this->band($estimates, SizeBand::A)->all->called;

        self::assertSame(0, $estimates->activityDuplicateRows);
        self::assertSame(1, $called->soldOuts);
        self::assertSame(1, $called->gpSampleSize);
    }

    public function testACallAfterTheRestockLeavesTheSoldOutAlone(): void
    {
        $estimates = $this->estimate(
            soldOuts: [
                $this->soldOut('S-after', restocked: '2025-09-12'),
                $this->soldOut('S-on', restocked: '2025-09-12'),
                $this->soldOut('S-before', restocked: '2025-09-12'),
                $this->soldOut('S-never'),
            ],
            activities: [
                $this->call('S-after', date: '2025-09-13'),
                $this->call('S-on', date: '2025-09-12'),
                $this->call('S-before', date: '2025-09-11'),
                $this->call('S-never', date: '2026-01-01'),
            ],
        );
        $all = $this->band($estimates, SizeBand::A)->all;

        self::assertSame(3, $all->called->soldOuts);
        self::assertSame('0.6667', $all->called->restockRate->central?->toString());
        self::assertSame(1, $all->leftAlone->soldOuts);
        self::assertSame('1.0000', $all->leftAlone->restockRate->central?->toString());
    }

    public function testTheFirstCallDecidesWhatEverTheOrderOfTheRows(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1', restocked: '2025-09-12')],
            activities: [$this->call('S1', date: '2025-09-20'), $this->call('S1', date: '2025-09-11')],
        );

        self::assertSame(1, $this->band($estimates, SizeBand::A)->all->called->soldOuts);
    }

    public function testSoldOutsWithAnActiveScheduleAreExcludedFromTheUnconfoundedCut(): void
    {
        $estimates = $this->estimate(
            soldOuts: [
                $this->soldOut('S-starts', date: '2025-09-10'),
                $this->soldOut('S-ends', date: '2025-09-10'),
                $this->soldOut('S-open', date: '2025-09-10'),
                $this->soldOut('S-ended', date: '2025-09-10'),
                $this->soldOut('S-later', date: '2025-09-10'),
                $this->soldOut('S-none', date: '2025-09-10'),
            ],
            schedules: [
                $this->schedule('S-starts', '2025-09-10', null),
                $this->schedule('S-ends', '2025-08-01', '2025-09-10'),
                $this->schedule('S-open', '2025-01-01', null),
                $this->schedule('S-ended', '2025-08-01', '2025-09-09'),
                $this->schedule('S-later', '2025-09-11', null),
            ],
        );
        $band = $this->band($estimates, SizeBand::A);

        self::assertSame(3, $estimates->soldOutsWithSchedule);
        self::assertSame(6, $band->all->leftAlone->soldOuts);
        self::assertSame(3, $band->unconfounded->leftAlone->soldOuts);
        self::assertSame([Confounder::AutoReplenishSchedule], $estimates->unconfoundedExcludes);
    }

    public function testSoldOutsAfterADealAlertSendInTheWeekBeforeAreExcluded(): void
    {
        $estimates = $this->estimate(
            soldOuts: [
                $this->soldOut('S-same-day', date: '2025-09-10'),
                $this->soldOut('S-7-days', date: '2025-09-10'),
                $this->soldOut('S-8-days', date: '2025-09-10'),
                $this->soldOut('S-day-after', date: '2025-09-10'),
                $this->soldOut('S-none', date: '2025-09-10'),
            ],
            dealAlertSends: [
                $this->send('S-same-day', '2025-09-10'),
                $this->send('S-7-days', '2025-09-03'),
                $this->send('S-8-days', '2025-09-02'),
                $this->send('S-day-after', '2025-09-11'),
            ],
        );

        self::assertSame(2, $estimates->soldOutsWithDealAlert);
        self::assertSame(3, $this->band($estimates, SizeBand::A)->unconfounded->leftAlone->soldOuts);
        self::assertSame([Confounder::DealAlertSend], $estimates->unconfoundedExcludes);
    }

    public function testAScheduleOnAnotherDealOfTheSameMerchantDoesNotExclude(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1'), $this->soldOut('S2')],
            schedules: [$this->schedule('S2', '2025-01-01', null)],
        );
        $unconfounded = $this->band($estimates, SizeBand::A)->unconfounded;

        self::assertSame(1, $estimates->soldOutsWithSchedule);
        self::assertSame(1, $unconfounded->leftAlone->soldOuts);
        self::assertSame(0, $unconfounded->called->soldOuts);
    }

    public function testASoldOutWithBothConfoundersIsExcludedOnce(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1'), $this->soldOut('S2'), $this->soldOut('S3')],
            activities: [$this->call('S1'), $this->call('S2')],
            schedules: [$this->schedule('S1', '2025-01-01', null)],
            dealAlertSends: [$this->send('S1', '2025-09-09')],
        );
        $band = $this->band($estimates, SizeBand::A);

        self::assertSame(1, $estimates->soldOutsWithSchedule);
        self::assertSame(1, $estimates->soldOutsWithDealAlert);
        self::assertSame(2, $band->all->called->soldOuts);
        self::assertSame(1, $band->unconfounded->called->soldOuts);
        self::assertSame(1, $band->unconfounded->leftAlone->soldOuts);
    }

    public function testWithoutScheduleOrDealAlertFilesTheUnconfoundedCutEqualsAll(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1', gpPence: 100), $this->soldOut('S2', gpPence: 300)],
            activities: [$this->call('S1')],
        );
        $band = $this->band($estimates, SizeBand::A);

        self::assertSame([], $estimates->unconfoundedExcludes);
        self::assertSame($band->all->toArray(), $band->unconfounded->toArray());
    }

    public function testSoldOutsAndMonthsOfMerchantsMissingFromMerchantsAreSkipped(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1'), $this->soldOut('S2', merchant: 'M-unknown')],
            merchantMonths: [$this->month(self::MERCHANT_A, 9000, 1000), $this->month('M-unknown', 100, 100)],
        );

        self::assertSame(2, $estimates->soldOuts);
        self::assertSame(1, $estimates->soldOutsWithoutBand);
        self::assertSame(1, $estimates->merchantMonths);
        self::assertSame(1, $this->band($estimates, SizeBand::A)->all->leftAlone->soldOuts);
    }

    public function testSoldOutsAreSplitByTheMerchantsBand(): void
    {
        $estimates = $this->estimate(
            soldOuts: [$this->soldOut('S1'), $this->soldOut('S2', merchant: self::MERCHANT_B)],
        );

        self::assertSame(1, $this->band($estimates, SizeBand::A)->all->leftAlone->soldOuts);
        self::assertSame(1, $this->band($estimates, SizeBand::B)->all->leftAlone->soldOuts);
        self::assertSame(0, $this->band($estimates, SizeBand::C)->all->leftAlone->soldOuts);
    }

    public function testRefundRateIsTheRatioOfSumsWithALinearisedStandardError(): void
    {
        $estimates = $this->estimate(
            merchantMonths: [$this->month(self::MERCHANT_A, 10000, 0), $this->month(self::MERCHANT_A, 8000, 2000)],
        );

        self::assertSame(
            ['n' => 2, 'central' => '0.1000', 'se' => '0.1000', 'low' => '0.0000', 'high' => '0.2960'],
            $this->band($estimates, SizeBand::A)->refundRate->toArray(),
        );
    }

    public function testRefundRateOfProportionalMonthsHasNoError(): void
    {
        $estimates = $this->estimate(
            merchantMonths: [$this->month(self::MERCHANT_A, 9000, 1000), $this->month(self::MERCHANT_A, 18000, 2000)],
        );

        self::assertSame(
            ['n' => 2, 'central' => '0.1000', 'se' => '0.0000', 'low' => '0.1000', 'high' => '0.1000'],
            $this->band($estimates, SizeBand::A)->refundRate->toArray(),
        );
    }

    public function testRefundRateOfASingleMonthHasNoInterval(): void
    {
        $estimates = $this->estimate(merchantMonths: [$this->month(self::MERCHANT_A, 2000, 1000)]);

        self::assertSame(
            ['n' => 1, 'central' => '0.3333', 'se' => null, 'low' => null, 'high' => null],
            $this->band($estimates, SizeBand::A)->refundRate->toArray(),
        );
    }

    public function testRefundRateWithoutGrossHasNoEstimate(): void
    {
        $estimates = $this->estimate(merchantMonths: [$this->month(self::MERCHANT_A, 0, 0)]);

        self::assertSame(
            ['n' => 1, 'central' => null, 'se' => null, 'low' => null, 'high' => null],
            $this->band($estimates, SizeBand::A)->refundRate->toArray(),
        );
        self::assertNull($this->band($estimates, SizeBand::B)->refundRate->central);
    }

    public function testOutputHasSortedKeysAndAllBands(): void
    {
        $output = $this->estimate()->toArray();

        self::assertSame(['bands', 'counts', 'notes', 'unconfounded_excludes'], array_keys($output));
        self::assertIsArray($output['bands']);
        self::assertSame(['A', 'B', 'C', 'D'], array_keys($output['bands']));
        self::assertIsArray($output['bands']['A']);
        self::assertSame(['all', 'refund_rate', 'unconfounded'], array_keys($output['bands']['A']));
        self::assertIsArray($output['counts']);
        $keys = array_keys($output['counts']);
        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys);
        self::assertIsArray($output['notes']);
        self::assertTrue(array_is_list($output['notes']));
    }

    /**
     * @param list<SoldOutRecord>            $soldOuts
     * @param list<ActivityRecord>           $activities
     * @param list<MerchantMonthRecord>      $merchantMonths
     * @param list<ScheduleRecord>|null      $schedules
     * @param list<DealAlertSendRecord>|null $dealAlertSends
     */
    private function estimate(
        array $soldOuts = [],
        array $activities = [],
        array $merchantMonths = [],
        ?array $schedules = null,
        ?array $dealAlertSends = null,
    ): HistoricalEstimates {
        $reader = self::createStub(DataPackReaderInterface::class);
        $reader->method('read')->willReturn(new HistoricalRecords(
            $soldOuts,
            $activities,
            [
                new MerchantRecord(self::MERCHANT_A, SizeBand::A),
                new MerchantRecord(self::MERCHANT_B, SizeBand::B),
            ],
            $merchantMonths,
            $schedules,
            $dealAlertSends,
        ));

        return new HistoricalEstimator($reader)->estimate(DataPackDirectory::fromPath(sys_get_temp_dir()));
    }

    private function band(HistoricalEstimates $estimates, SizeBand $band): BandEstimates
    {
        foreach ($estimates->bands as $estimate) {
            if ($estimate->band === $band) {
                return $estimate;
            }
        }

        self::fail(\sprintf('Band %s is missing.', $band->value));
    }

    private function soldOut(
        string $id,
        string $merchant = self::MERCHANT_A,
        string $date = '2025-09-10',
        ?string $restocked = null,
        ?int $gpPence = null,
    ): SoldOutRecord {
        return new SoldOutRecord(
            $id,
            'D-'.$id,
            $merchant,
            new \DateTimeImmutable($date),
            $restocked === null ? null : new \DateTimeImmutable($restocked),
            $gpPence === null ? null : Money::fromPence($gpPence),
        );
    }

    private function call(string $soldOutId, string $date = '2025-09-10', string $duration = '300'): ActivityRecord
    {
        return $this->activity($soldOutId, ActivityType::Call, $date, $duration);
    }

    private function activity(
        ?string $soldOutId,
        ActivityType $type,
        string $date = '2025-09-10',
        string $duration = '300',
    ): ActivityRecord {
        return new ActivityRecord(self::MERCHANT_A, $soldOutId, new \DateTimeImmutable($date), $type, 'R1', $duration);
    }

    private function schedule(string $soldOutId, string $start, ?string $end): ScheduleRecord
    {
        return new ScheduleRecord(
            'D-'.$soldOutId,
            new \DateTimeImmutable($start),
            $end === null ? null : new \DateTimeImmutable($end),
        );
    }

    private function send(string $soldOutId, string $date): DealAlertSendRecord
    {
        return new DealAlertSendRecord('D-'.$soldOutId, new \DateTimeImmutable($date));
    }

    private function month(string $merchant, int $gpPence, int $refundsPence): MerchantMonthRecord
    {
        return new MerchantMonthRecord($merchant, Money::fromPence($gpPence), Money::fromPence($refundsPence));
    }
}
