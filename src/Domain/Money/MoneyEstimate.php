<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class MoneyEstimate
{
    public function __construct(
        public ?Money $central,
        public ?Money $standardError,
        public ?Money $low,
        public ?Money $high,
    ) {
    }

    /**
     * @return array<string, int|null>
     */
    public function toArray(): array
    {
        return [
            'central' => $this->central?->toPence(),
            'se' => $this->standardError?->toPence(),
            'low' => $this->low?->toPence(),
            'high' => $this->high?->toPence(),
        ];
    }
}
