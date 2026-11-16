<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class DealAlertSendRecord
{
    private const int DAYS_BEFORE_SOLD_OUT = 7;

    public function __construct(
        public string $dealId,
        public \DateTimeImmutable $sentDate,
    ) {
    }

    public function precedes(\DateTimeImmutable $soldOutDate): bool
    {
        return $this->sentDate <= $soldOutDate
            && $this->sentDate >= $soldOutDate->modify(\sprintf('-%d days', self::DAYS_BEFORE_SOLD_OUT));
    }
}
