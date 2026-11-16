<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

use App\Domain\Money\Money;

final readonly class RecommendedSoldOut
{
    public function __construct(
        public SoldOutCase $case,
        public Recommendation $recommendation,
        public string $reason,
        public Money $netLow,
        public Money $netCentral,
        public Money $netHigh,
        public bool $hasNetValue = true,
    ) {
    }
}
