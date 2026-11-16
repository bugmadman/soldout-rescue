<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class RateEstimate
{
    public function __construct(
        public int $n,
        public ?Ratio $central,
        public ?Ratio $standardError,
        public ?Ratio $low,
        public ?Ratio $high,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'n' => $this->n,
            'central' => $this->central?->toString(),
            'se' => $this->standardError?->toString(),
            'low' => $this->low?->toString(),
            'high' => $this->high?->toString(),
        ];
    }
}
