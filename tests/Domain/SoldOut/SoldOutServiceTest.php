<?php

declare(strict_types=1);

namespace App\Tests\Domain\SoldOut;

use App\Domain\Money\MerchantRecord;
use App\Domain\Money\Money;
use App\Domain\Money\MoneyEstimate;
use App\Domain\Money\RoundingMode;
use App\Domain\Money\SizeBand;
use App\Domain\Money\SoldOutRecord;
use App\Domain\SoldOut\CallGainEstimates;
use App\Domain\SoldOut\Recommendation;
use App\Domain\SoldOut\RecommendedSoldOut;
use App\Domain\SoldOut\RecommendedSoldOuts;
use App\Domain\SoldOut\SoldOutCase;
use App\Domain\SoldOut\SoldOutCases;
use App\Domain\SoldOut\SoldOutService;
use App\Tests\Infrastructure\Sandbox\FakeSandboxClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoldOutService::class)]
#[CoversClass(RecommendedSoldOuts::class)]
#[CoversClass(CallGainEstimates::class)]
#[CoversClass(Recommendation::class)]
#[UsesClass(Money::class)]
#[UsesClass(MoneyEstimate::class)]
#[UsesClass(RoundingMode::class)]
#[UsesClass(RecommendedSoldOut::class)]
#[UsesClass(SoldOutCase::class)]
#[UsesClass(SoldOutCases::class)]
final class SoldOutServiceTest extends TestCase
{
    private const string DAY = '2030-01-15';

    public function testNetValueIsTheBandGainMinusRepTime(): void
    {
        $item = $this->only($this->service($this->gains(), [$this->case('SO-1', SizeBand::A)])->recommendationsOn(
            new \DateTimeImmutable(self::DAY.' 09:30'),
        ));

        self::assertSame(Recommendation::Call, $item->recommendation);
        self::assertSame(-100, $item->netLow->toPence());
        self::assertSame(100, $item->netCentral->toPence());
        self::assertSame(300, $item->netHigh->toPence());
        self::assertSame(
            'Expected GP gain of a call exceeds the cost of rep time: £5.00 (range £3.00 to £7.00) against £4.00, '
            .'net £1.00. Observational estimate for size band A, not a causal effect.',
            $item->reason,
        );
    }

    public function testEachSoldOutUsesTheGainOfItsOwnBand(): void
    {
        $items = $this->ids(
            $this->service(
                $this->gains(),
                [$this->case('SO-A', SizeBand::A), $this->case('SO-B', SizeBand::B)],
            )->recommendationsOn(new \DateTimeImmutable(self::DAY)),
            static fn (RecommendedSoldOut $item): string => $item->netCentral->toPence().'/'.$item->netHigh->toPence(),
        );

        self::assertSame(['SO-B' => '1600/3600', 'SO-A' => '100/300'], $items);
    }

    public function testDoNotContactAlwaysWins(): void
    {
        $item = $this->only($this->service(
            $this->gains(),
            [$this->case('SO-1', SizeBand::B, doNotContact: true)],
        )->recommendationsOn(new \DateTimeImmutable(self::DAY)));

        self::assertSame(Recommendation::DontCall, $item->recommendation);
        self::assertSame('The merchant is marked do not contact.', $item->reason);
        self::assertSame(0, $item->netLow->toPence());
        self::assertSame(0, $item->netCentral->toPence());
        self::assertSame(0, $item->netHigh->toPence());
    }

    public function testNegativeNetIsDontCallWithTheNumbers(): void
    {
        $item = $this->only($this->service(
            $this->gains(),
            [$this->case('SO-1', SizeBand::C)],
        )->recommendationsOn(new \DateTimeImmutable(self::DAY)));

        self::assertSame(Recommendation::DontCall, $item->recommendation);
        self::assertSame(-451, $item->netLow->toPence());
        self::assertSame(-370, $item->netCentral->toPence());
        self::assertSame(-289, $item->netHigh->toPence());
        self::assertSame(
            'Expected GP gain of a call is below the cost of rep time: £0.30 (range -£0.51 to £1.11) against £4.00, '
            .'net -£3.70. Observational estimate for size band C, not a causal effect.',
            $item->reason,
        );
    }

    public function testZeroNetIsDontCall(): void
    {
        $item = $this->only($this->service(
            $this->gains(),
            [$this->case('SO-1', SizeBand::D)],
        )->recommendationsOn(new \DateTimeImmutable(self::DAY)));

        self::assertSame(Recommendation::DontCall, $item->recommendation);
        self::assertSame(0, $item->netCentral->toPence());
        self::assertStringStartsWith('Expected GP gain of a call equals the cost of rep time: £4.00', $item->reason);
    }

    /**
     * @return iterable<string, array{MoneyEstimate|null}>
     */
    public static function missingGains(): iterable
    {
        yield 'band absent' => [null];
        yield 'no central' => [new MoneyEstimate(null, null, Money::fromPence(1000), Money::fromPence(3000))];
        yield 'no low' => [new MoneyEstimate(Money::fromPence(2000), null, null, Money::fromPence(3000))];
        yield 'no high' => [new MoneyEstimate(Money::fromPence(2000), null, Money::fromPence(1000), null)];
    }

    #[DataProvider('missingGains')]
    public function testMissingEstimateIsDontCall(?MoneyEstimate $gain): void
    {
        $gains = $gain === null ? CallGainEstimates::none() : CallGainEstimates::none()->with(SizeBand::B, $gain);

        $item = $this->only($this->service($gains, [$this->case('SO-1', SizeBand::B)])->recommendationsOn(
            new \DateTimeImmutable(self::DAY),
        ));

        self::assertSame(Recommendation::DontCall, $item->recommendation);
        self::assertSame('No historical estimate of the GP gain of a call for size band B.', $item->reason);
        self::assertSame(0, $item->netCentral->toPence());
    }

    public function testRepTimeCostParametersChangeTheResult(): void
    {
        $case = $this->case('SO-1', SizeBand::C);

        $cheap = $this->only(
            $this->service($this->gains(), [$case], minutes: 6, ratePence: 200)
                ->recommendationsOn(new \DateTimeImmutable(self::DAY)),
        );

        self::assertSame(Recommendation::Call, $cheap->recommendation);
        self::assertSame(10, $cheap->netCentral->toPence());
        self::assertStringContainsString('against £0.20, net £0.10.', $cheap->reason);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function repTimeCosts(): iterable
    {
        yield 'default 12 min at £20' => [12, 2000, 400];
        yield '7 min at £20 rounds down' => [7, 2000, 233];
        yield '1 min at £20.30 rounds up' => [1, 2030, 34];
        yield 'exact half rounds away from zero' => [3, 10, 1];
        yield 'zero minutes' => [0, 2000, 0];
        yield 'zero rate' => [12, 0, 0];
    }

    #[DataProvider('repTimeCosts')]
    public function testRepTimeCostIsRoundedToWholePence(int $minutes, int $ratePence, int $costPence): void
    {
        $zeroGain = CallGainEstimates::none()->with(
            SizeBand::A,
            new MoneyEstimate(Money::zero(), null, Money::zero(), Money::zero()),
        );

        $item = $this->only(
            $this->service($zeroGain, [$this->case('SO-1', SizeBand::A)], $minutes, $ratePence)
                ->recommendationsOn(new \DateTimeImmutable(self::DAY)),
        );

        self::assertSame(-$costPence, $item->netCentral->toPence());
        self::assertSame(Recommendation::DontCall, $item->recommendation);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function negativeRepTime(): iterable
    {
        yield 'negative minutes' => [-1, 2000];
        yield 'negative rate' => [12, -1];
    }

    #[DataProvider('negativeRepTime')]
    public function testNegativeRepTimeIsRejected(int $minutes, int $ratePence): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(\sprintf(
            'Rep time must not be negative, got %d minutes per task at %d pence per hour.',
            $minutes,
            $ratePence,
        ));

        $this->service($this->gains(), minutes: $minutes, ratePence: $ratePence);
    }

    public function testCallsComeFirstByNetThenDontCallsTiesBySoldOutId(): void
    {
        $service = $this->service(
            $this->gains(),
            [
                $this->case('SO-7', SizeBand::C),
                $this->case('SO-2', SizeBand::A),
                $this->case('SO-6', SizeBand::B, doNotContact: true),
                $this->case('SO-5', SizeBand::B),
                $this->case('SO-1', SizeBand::A),
                $this->case('SO-3', SizeBand::D),
                $this->case('SO-4', SizeBand::B),
            ],
        );

        $ranked = $this->ids(
            $service->recommendationsOn(new \DateTimeImmutable(self::DAY)),
            static fn (RecommendedSoldOut $item): string => $item->recommendation->label(),
        );

        self::assertSame(
            [
                'SO-4' => 'Call',
                'SO-5' => 'Call',
                'SO-1' => 'Call',
                'SO-2' => 'Call',
                'SO-3' => "Don't call",
                'SO-6' => "Don't call",
                'SO-7' => "Don't call",
            ],
            $ranked,
        );
    }

    public function testOnlyThatDaysSoldOutsAreRecommended(): void
    {
        $service = $this->service(
            $this->gains(),
            [$this->case('SO-1', SizeBand::A), $this->case('SO-2', SizeBand::A, '2030-01-16')],
        );

        self::assertSame(
            ['SO-2' => 'Call'],
            $this->ids(
                $service->recommendationsOn(new \DateTimeImmutable('2030-01-16')),
                static fn (RecommendedSoldOut $item): string => $item->recommendation->label(),
            ),
        );
    }

    public function testDayWithoutSoldOutsGivesNoRecommendations(): void
    {
        $service = $this->service($this->gains(), [$this->case('SO-1', SizeBand::A)]);

        self::assertCount(0, $service->recommendationsOn(new \DateTimeImmutable('2030-01-14')));
    }

    public function testLatestSoldOutDateComesFromTheSandbox(): void
    {
        $service = $this->service(
            $this->gains(),
            [
                $this->case('SO-1', SizeBand::A, '2030-01-16'),
                $this->case('SO-2', SizeBand::A, '2030-01-20'),
                $this->case('SO-3', SizeBand::A, '2030-01-15'),
            ],
        );

        self::assertSame('2030-01-20', $service->latestSoldOutDate()?->format('Y-m-d'));
    }

    public function testNoSoldOutsMeansNoLatestDate(): void
    {
        self::assertNull($this->service($this->gains())->latestSoldOutDate());
    }

    public function testRecommendationLabels(): void
    {
        self::assertSame('Call', Recommendation::Call->label());
        self::assertSame("Don't call", Recommendation::DontCall->label());
    }

    private function gains(): CallGainEstimates
    {
        return CallGainEstimates::none()
            ->with(SizeBand::A, $this->gain(300, 500, 700))
            ->with(SizeBand::B, $this->gain(1000, 2000, 4000))
            ->with(SizeBand::C, $this->gain(-51, 30, 111))
            ->with(SizeBand::D, $this->gain(400, 400, 400));
    }

    private function gain(int $low, int $central, int $high): MoneyEstimate
    {
        return new MoneyEstimate(
            Money::fromPence($central),
            Money::fromPence(100),
            Money::fromPence($low),
            Money::fromPence($high),
        );
    }

    /**
     * @param list<SoldOutCase> $cases
     */
    private function service(
        CallGainEstimates $gains,
        array $cases = [],
        int $minutes = 12,
        int $ratePence = 2000,
    ): SoldOutService {
        return new SoldOutService(new FakeSandboxClient(...$cases), $gains, $minutes, $ratePence);
    }

    private function case(
        string $soldOutId,
        SizeBand $band,
        string $day = self::DAY,
        bool $doNotContact = false,
    ): SoldOutCase {
        $merchantId = 'M-'.$soldOutId;

        return new SoldOutCase(
            new SoldOutRecord($soldOutId, 'D-'.$soldOutId, $merchantId, new \DateTimeImmutable($day), null, null),
            new MerchantRecord($merchantId, $band),
            'Invented Merchant',
            'Testford',
            'R1',
            $doNotContact,
        );
    }

    private function only(RecommendedSoldOuts $items): RecommendedSoldOut
    {
        self::assertCount(1, $items);
        foreach ($items as $item) {
            return $item;
        }

        self::fail('No recommendation.');
    }

    /**
     * @param \Closure(RecommendedSoldOut): string $describe
     *
     * @return array<string, string>
     */
    private function ids(RecommendedSoldOuts $items, \Closure $describe): array
    {
        $described = [];
        foreach ($items as $item) {
            $described[$item->case->soldOut->soldOutId] = $describe($item);
        }

        return $described;
    }
}
