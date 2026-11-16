<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class ActivityRecord
{
    public function __construct(
        public string $merchantId,
        public ?string $soldOutId,
        public \DateTimeImmutable $activityDate,
        public ActivityType $type,
        public string $ownerId,
        public string $durationSeconds,
    ) {
    }

    public function replayKey(): string
    {
        return json_encode(
            [
                $this->merchantId,
                $this->soldOutId,
                $this->activityDate->format('Y-m-d'),
                $this->type->value,
                $this->ownerId,
                $this->durationSeconds,
            ],
            \JSON_THROW_ON_ERROR,
        );
    }
}
