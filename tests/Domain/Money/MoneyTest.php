<?php

declare(strict_types=1);

namespace App\Tests\Domain\Money;

use App\Domain\Money\Money;
use App\Domain\Money\RoundingMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
#[CoversClass(RoundingMode::class)]
final class MoneyTest extends TestCase
{
    public function testFromPenceKeepsTheAmount(): void
    {
        self::assertSame(-350, Money::fromPence(-350)->toPence());
    }

    public function testZeroIsZeroPence(): void
    {
        self::assertSame(0, Money::zero()->toPence());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validPounds(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'negative zero' => ['-0.00', 0];
        yield 'whole pounds' => ['79', 7900];
        yield 'two decimals' => ['154.39', 15439];
        yield 'trailing zeros' => ['19.00', 1900];
        yield 'one decimal' => ['0.1', 10];
        yield 'leading zeros' => ['007.5', 750];
        yield 'negative' => ['-2.50', -250];
        yield 'half rounds up' => ['1.005', 101];
        yield 'below half rounds down' => ['1.0049999', 100];
        yield 'above half rounds up' => ['1.0050001', 101];
        yield 'negative half rounds away from zero' => ['-1.005', -101];
        yield 'negative below half rounds towards zero' => ['-1.004', -100];
        yield 'smallest half' => ['0.005', 1];
        yield 'smallest negative half' => ['-0.005', -1];
        yield 'below a half penny' => ['-0.004', 0];
        yield 'rounding carries into pounds' => ['0.995', 100];
        yield 'surrounding whitespace' => [" 12.34\t", 1234];
        yield 'long fraction' => ['1.'.str_repeat('9', 40), 200];
        yield 'largest whole pounds' => ['92233720368547758', 9223372036854775800];
        yield 'largest amount' => ['92233720368547758.07', \PHP_INT_MAX];
    }

    #[DataProvider('validPounds')]
    public function testFromPoundsParsesDecimalStrings(string $pounds, int $expectedPence): void
    {
        self::assertSame($expectedPence, Money::fromPounds($pounds)->toPence());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPounds(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
        yield 'letters' => ['abc'];
        yield 'thousands separator' => ['1,000.00'];
        yield 'currency symbol' => ['£5.00'];
        yield 'comma decimal' => ['5,50'];
        yield 'plus sign' => ['+5'];
        yield 'missing whole part' => ['.5'];
        yield 'missing fraction' => ['5.'];
        yield 'exponent' => ['1e3'];
        yield 'inner space' => ['1 000'];
        yield 'two signs' => ['--1'];
        yield 'trailing text' => ['5.00 GBP'];
        yield 'non-ascii digit' => ['٥'];
    }

    #[DataProvider('invalidPounds')]
    public function testFromPoundsRejectsNonDecimalInput(string $pounds): void
    {
        $this->expectException(\DomainException::class);

        Money::fromPounds($pounds);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oversizedPounds(): iterable
    {
        yield 'just above the largest amount' => ['92233720368547758.08'];
        yield 'too many whole digits' => ['1'.str_repeat('0', 18)];
        yield 'negative overflow' => ['-92233720368547759'];
        yield 'smallest amount is not parsable' => ['-92233720368547758.08'];
        yield 'rounding carry overflows' => ['92233720368547758.075'];
    }

    #[DataProvider('oversizedPounds')]
    public function testFromPoundsRejectsAmountsOutsideTheIntegerRange(string $pounds): void
    {
        $this->expectException(\DomainException::class);

        Money::fromPounds($pounds);
    }

    public function testAdd(): void
    {
        self::assertSame(1200, Money::fromPence(1550)->add(Money::fromPence(-350))->toPence());
    }

    public function testSubtractCanGoNegative(): void
    {
        self::assertSame(-350, Money::fromPence(150)->subtract(Money::fromPence(500))->toPence());
    }

    public function testSubtractOfTheSmallestIntegerWhenTheResultFits(): void
    {
        self::assertSame(\PHP_INT_MAX, Money::fromPence(-1)->subtract(Money::fromPence(\PHP_INT_MIN))->toPence());
    }

    public function testNegate(): void
    {
        self::assertSame(-350, Money::fromPence(350)->negate()->toPence());
        self::assertSame(350, Money::fromPence(-350)->negate()->toPence());
        self::assertSame(0, Money::zero()->negate()->toPence());
    }

    /**
     * @return iterable<string, array{\Closure(): Money}>
     */
    public static function overflows(): iterable
    {
        yield 'add above max' => [static fn (): Money => Money::fromPence(\PHP_INT_MAX)->add(Money::fromPence(1))];
        yield 'add below min' => [static fn (): Money => Money::fromPence(\PHP_INT_MIN)->add(Money::fromPence(-1))];
        yield 'subtract below min' => [
            static fn (): Money => Money::fromPence(\PHP_INT_MIN)->subtract(Money::fromPence(1)),
        ];
        yield 'subtract above max' => [
            static fn (): Money => Money::fromPence(0)->subtract(Money::fromPence(\PHP_INT_MIN)),
        ];
        yield 'negate min' => [static fn (): Money => Money::fromPence(\PHP_INT_MIN)->negate()];
        yield 'multiply above max' => [
            static fn (): Money => Money::fromPence(\PHP_INT_MAX)->multiplyBy('2', RoundingMode::HalfAwayFromZero),
        ];
        yield 'multiply below min' => [
            static fn (): Money => Money::fromPence(\PHP_INT_MAX)->multiplyBy('-2', RoundingMode::HalfAwayFromZero),
        ];
        yield 'intermediate product overflows' => [
            static fn (): Money => Money::fromPence(\PHP_INT_MAX)->multiplyBy('1.0', RoundingMode::HalfAwayFromZero),
        ];
    }

    /**
     * @param \Closure(): Money $operation
     */
    #[DataProvider('overflows')]
    public function testOverflowThrows(\Closure $operation): void
    {
        $this->expectException(\DomainException::class);

        $operation();
    }

    /**
     * @return iterable<string, array{int, string, RoundingMode, int}>
     */
    public static function multiplications(): iterable
    {
        yield 'whole factor' => [250, '3', RoundingMode::HalfAwayFromZero, 750];
        yield 'zero factor' => [250, '0', RoundingMode::HalfAwayFromZero, 0];
        yield 'zero amount' => [0, '0.37', RoundingMode::Ceil, 0];
        yield 'exact decimal' => [1000, '0.25', RoundingMode::Floor, 250];
        yield 'negative factor' => [1000, '-0.5', RoundingMode::HalfAwayFromZero, -500];
        yield 'many decimals' => [100000, '0.333333', RoundingMode::HalfAwayFromZero, 33333];

        yield 'half away: positive half' => [5, '0.5', RoundingMode::HalfAwayFromZero, 3];
        yield 'half away: negative half' => [-5, '0.5', RoundingMode::HalfAwayFromZero, -3];
        yield 'half away: positive below half' => [7, '0.3', RoundingMode::HalfAwayFromZero, 2];
        yield 'half away: negative below half' => [-7, '0.3', RoundingMode::HalfAwayFromZero, -2];
        yield 'half away: positive above half' => [9, '0.3', RoundingMode::HalfAwayFromZero, 3];
        yield 'half away: negative above half' => [-9, '0.3', RoundingMode::HalfAwayFromZero, -3];
        yield 'half away: negative factor half' => [5, '-0.5', RoundingMode::HalfAwayFromZero, -3];

        yield 'floor: positive half' => [5, '0.5', RoundingMode::Floor, 2];
        yield 'floor: negative half' => [-5, '0.5', RoundingMode::Floor, -3];
        yield 'floor: positive above half' => [9, '0.3', RoundingMode::Floor, 2];
        yield 'floor: negative below half' => [-7, '0.3', RoundingMode::Floor, -3];

        yield 'ceil: positive half' => [5, '0.5', RoundingMode::Ceil, 3];
        yield 'ceil: negative half' => [-5, '0.5', RoundingMode::Ceil, -2];
        yield 'ceil: positive below half' => [7, '0.3', RoundingMode::Ceil, 3];
        yield 'ceil: negative above half' => [-9, '0.3', RoundingMode::Ceil, -2];

        yield 'largest factor precision' => [100000, '0.333333333', RoundingMode::HalfAwayFromZero, 33333];
        yield 'exact negative product floor' => [-5, '0.2', RoundingMode::Floor, -1];
        yield 'exact negative product ceil' => [-5, '0.2', RoundingMode::Ceil, -1];
        yield 'one at the smallest amount' => [\PHP_INT_MIN, '1', RoundingMode::HalfAwayFromZero, \PHP_INT_MIN];
        yield 'surrounding whitespace' => [200, ' 1.5 ', RoundingMode::HalfAwayFromZero, 300];
    }

    #[DataProvider('multiplications')]
    public function testMultiplyBy(int $pence, string $factor, RoundingMode $rounding, int $expectedPence): void
    {
        self::assertSame($expectedPence, Money::fromPence($pence)->multiplyBy($factor, $rounding)->toPence());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFactors(): iterable
    {
        yield 'blank' => [' '];
        yield 'letters' => ['abc'];
        yield 'percent' => ['15%'];
        yield 'exponent' => ['1e-2'];
        yield 'thousands separator' => ['1,000'];
        yield 'too many decimal places' => ['0.0000000001'];
        yield 'too many digits' => [str_repeat('9', 10)];
        yield 'whole and fraction digits together' => ['12345.123456'];
    }

    #[DataProvider('invalidFactors')]
    public function testMultiplyByRejectsInvalidFactors(string $factor): void
    {
        $this->expectException(\DomainException::class);

        Money::fromPence(100)->multiplyBy($factor, RoundingMode::HalfAwayFromZero);
    }

    public function testCompareTo(): void
    {
        self::assertSame(-1, Money::fromPence(-1)->compareTo(Money::zero()));
        self::assertSame(0, Money::fromPence(5)->compareTo(Money::fromPence(5)));
        self::assertSame(1, Money::fromPence(6)->compareTo(Money::fromPence(5)));
    }

    public function testIsNegative(): void
    {
        self::assertTrue(Money::fromPence(-1)->isNegative());
        self::assertFalse(Money::zero()->isNegative());
        self::assertFalse(Money::fromPence(1)->isNegative());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function formats(): iterable
    {
        yield 'zero' => [0, '£0.00'];
        yield 'pence only' => [5, '£0.05'];
        yield 'negative pence only' => [-5, '-£0.05'];
        yield 'pounds and pence' => [15439, '£154.39'];
        yield 'negative' => [-350, '-£3.50'];
        yield 'whole pounds' => [7900, '£79.00'];
        yield 'large amount' => [123456789, '£1234567.89'];
        yield 'largest amount' => [\PHP_INT_MAX, '£92233720368547758.07'];
        yield 'smallest amount' => [\PHP_INT_MIN, '-£92233720368547758.08'];
    }

    #[DataProvider('formats')]
    public function testFormat(int $pence, string $expected): void
    {
        self::assertSame($expected, Money::fromPence($pence)->format());
    }

    public function testOperationsReturnNewInstancesAndLeaveTheOriginalUnchanged(): void
    {
        $original = Money::fromPence(1000);
        $other = Money::fromPence(300);

        $results = [
            $original->add($other),
            $original->subtract($other),
            $original->negate(),
            $original->multiplyBy('1', RoundingMode::HalfAwayFromZero),
        ];

        foreach ($results as $result) {
            self::assertNotSame($original, $result);
        }
        self::assertSame(1000, $original->toPence());
        self::assertSame(300, $other->toPence());
    }

    public function testRoundingModeRejectsANonPositiveDivisor(): void
    {
        $this->expectException(\LogicException::class);

        RoundingMode::Floor->divide(1, 0);
    }
}
