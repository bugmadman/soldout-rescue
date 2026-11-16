<?php

declare(strict_types=1);

namespace App\Tests\Domain\Money;

use App\Domain\Money\Ratio;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ratio::class)]
final class RatioTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function fractions(): iterable
    {
        yield 'zero' => [0, 5, '0.0000'];
        yield 'one' => [3, 3, '1.0000'];
        yield 'exact' => [1, 8, '0.1250'];
        yield 'repeating rounds up' => [2, 3, '0.6667'];
        yield 'repeating rounds down' => [1, 3, '0.3333'];
        yield 'half rounds away from zero' => [1, 32, '0.0313'];
        yield 'negative half rounds away from zero' => [-1, 32, '-0.0313'];
        yield 'above one' => [5, 4, '1.2500'];
        yield 'negative above one' => [-3, 2, '-1.5000'];
        yield 'tiny negative rounds to zero' => [-1, 30000, '0.0000'];
    }

    #[DataProvider('fractions')]
    public function testFromFractionRoundsToFourDecimals(int $numerator, int $denominator, string $expected): void
    {
        self::assertSame($expected, Ratio::fromFraction($numerator, $denominator)->toString());
    }

    public function testZeroDenominatorIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        Ratio::fromFraction(1, 0);
    }

    public function testNegativeDenominatorIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        Ratio::fromFraction(1, -2);
    }

    public function testNumeratorThatWouldOverflowIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        Ratio::fromFraction(\PHP_INT_MAX, \PHP_INT_MAX);
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function floats(): iterable
    {
        yield 'zero' => [0.0, '0.0000'];
        yield 'half rounds away from zero' => [0.03125, '0.0313'];
        yield 'below half rounds down' => [0.12344, '0.1234'];
        yield 'negative below half is zero' => [-0.00004, '0.0000'];
        yield 'negative half rounds away from zero' => [-0.00005, '-0.0001'];
        yield 'above one' => [2.5, '2.5000'];
    }

    #[DataProvider('floats')]
    public function testFromFloatRoundsToFourDecimals(float $value, string $expected): void
    {
        self::assertSame($expected, Ratio::fromFloat($value)->toString());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFiniteFloats(): iterable
    {
        yield 'nan' => [\NAN];
        yield 'infinity' => [\INF];
        yield 'negative infinity' => [-\INF];
    }

    #[DataProvider('nonFiniteFloats')]
    public function testNonFiniteFloatIsABug(float $value): void
    {
        try {
            Ratio::fromFloat($value);
            self::fail('A non-finite ratio was accepted.');
        } catch (\LogicException $e) {
            // \DomainException extends \LogicException, so expectException() would not tell them apart.
            self::assertSame(\LogicException::class, $e::class);
        }
    }

    public function testHugeFloatIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        Ratio::fromFloat(1e30);
    }
}
