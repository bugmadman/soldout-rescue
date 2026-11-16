<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class BandEstimates
{
    public function __construct(
        public SizeBand $band,
        public CutEstimates $all,
        public CutEstimates $unconfounded,
        public RateEstimate $refundRate,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'all' => $this->all->toArray(),
            'unconfounded' => $this->unconfounded->toArray(),
            'refund_rate' => $this->refundRate->toArray(),
        ];
    }
}
