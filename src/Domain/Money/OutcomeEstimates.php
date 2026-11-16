<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class OutcomeEstimates
{
    public function __construct(
        public int $soldOuts,
        public RateEstimate $restockRate,
        public int $gpSampleSize,
        public MoneyEstimate $gp30dAfter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sold_outs' => $this->soldOuts,
            'restock_rate' => $this->restockRate->toArray(),
            'gp_30d_after_pence' => ['n' => $this->gpSampleSize] + $this->gp30dAfter->toArray(),
        ];
    }
}
