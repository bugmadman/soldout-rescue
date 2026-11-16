<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class MerchantMonthRecord
{
    public function __construct(
        public string $merchantId,
        public Money $gp,
        public Money $refunds,
    ) {
    }
}
