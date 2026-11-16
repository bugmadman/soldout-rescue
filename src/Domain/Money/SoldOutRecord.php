<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class SoldOutRecord
{
    public function __construct(
        public string $soldOutId,
        public string $dealId,
        public string $merchantId,
        public \DateTimeImmutable $soldOutDate,
        public ?\DateTimeImmutable $restockedDate,
        public ?Money $gp30dAfter,
    ) {
    }

    public function isRestocked(): bool
    {
        return $this->restockedDate !== null;
    }

    public function couldBeCausedByCallOn(\DateTimeImmutable $callDate): bool
    {
        return $this->restockedDate === null || $callDate <= $this->restockedDate;
    }
}
