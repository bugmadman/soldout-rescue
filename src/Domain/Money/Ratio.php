<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class Ratio
{
    private const int SCALE = 10000;

    private function __construct(
        private int $tenThousandths,
    ) {
    }

    public static function fromFraction(int $numerator, int $denominator): self
    {
        if ($denominator <= 0) {
            throw new \DomainException(\sprintf('Ratio denominator must be positive, got %d.', $denominator));
        }
        if (abs($numerator) > intdiv(\PHP_INT_MAX, self::SCALE)) {
            throw new \DomainException(\sprintf('Ratio numerator %d is too large.', $numerator));
        }

        return new self(RoundingMode::HalfAwayFromZero->divide($numerator * self::SCALE, $denominator));
    }

    public static function fromFloat(float $value): self
    {
        if (!is_finite($value)) {
            throw new \LogicException(\sprintf('Ratio must be a finite number, got %F.', $value));
        }
        $scaled = round($value * self::SCALE);
        if (abs($scaled) > intdiv(\PHP_INT_MAX, self::SCALE)) {
            throw new \DomainException(\sprintf('Ratio %F is out of range.', $value));
        }

        return new self((int) $scaled);
    }

    public function toString(): string
    {
        return \sprintf(
            '%s%d.%04d',
            $this->tenThousandths < 0 ? '-' : '',
            abs(intdiv($this->tenThousandths, self::SCALE)),
            abs($this->tenThousandths % self::SCALE),
        );
    }
}
