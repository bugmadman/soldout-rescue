<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class MerchantRecord
{
    public function __construct(
        public string $merchantId,
        public SizeBand $sizeBand,
    ) {
    }
}
