<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class CutEstimates
{
    public function __construct(
        public OutcomeEstimates $called,
        public OutcomeEstimates $leftAlone,
        public MoneyEstimate $gp30dAfterDifference,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'called' => $this->called->toArray(),
            'left_alone' => $this->leftAlone->toArray(),
            'gp_30d_after_difference_pence' => $this->gp30dAfterDifference->toArray(),
        ];
    }
}
