<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

use App\Domain\Money\MoneyEstimate;
use App\Domain\Money\SizeBand;

final readonly class CallGainEstimates
{
    /**
     * @param array<string, MoneyEstimate> $byBand
     */
    private function __construct(
        private array $byBand,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function with(SizeBand $band, MoneyEstimate $gain): self
    {
        return new self([$band->value => $gain] + $this->byBand);
    }

    public function forBand(SizeBand $band): ?MoneyEstimate
    {
        return $this->byBand[$band->value] ?? null;
    }
}
