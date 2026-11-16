<?php

declare(strict_types=1);

namespace App\Domain\Money;

enum RoundingMode
{
    case HalfAwayFromZero;
    case Floor;
    case Ceil;

    public function divide(int $dividend, int $divisor): int
    {
        if ($divisor <= 0) {
            throw new \LogicException(\sprintf('Divisor must be positive, got %d.', $divisor));
        }

        $quotient = intdiv($dividend, $divisor);
        $remainder = $dividend % $divisor;

        if ($remainder === 0) {
            return $quotient;
        }

        return match ($this) {
            self::HalfAwayFromZero => 2 * abs($remainder) >= $divisor
                ? $quotient + ($remainder > 0 ? 1 : -1)
                : $quotient,
            self::Floor => $remainder < 0 ? $quotient - 1 : $quotient,
            self::Ceil => $remainder > 0 ? $quotient + 1 : $quotient,
        };
    }
}
