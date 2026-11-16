<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class ScheduleRecord
{
    public function __construct(
        public string $dealId,
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
    ) {
    }

    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        return $this->startDate <= $date && ($this->endDate === null || $this->endDate >= $date);
    }
}
