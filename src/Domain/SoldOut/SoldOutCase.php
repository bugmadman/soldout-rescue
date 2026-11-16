<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

use App\Domain\Money\MerchantRecord;
use App\Domain\Money\SoldOutRecord;

final readonly class SoldOutCase
{
    public function __construct(
        public SoldOutRecord $soldOut,
        public MerchantRecord $merchant,
        public string $merchantName,
        public string $city,
        public ?string $repId,
        public bool $doNotContact,
    ) {
        if ($soldOut->merchantId !== $merchant->merchantId) {
            $message = \sprintf(
                'Sold-out "%s" belongs to merchant "%s", not "%s".',
                $soldOut->soldOutId,
                $soldOut->merchantId,
                $merchant->merchantId,
            );
            throw new \LogicException($message);
        }
    }
}
