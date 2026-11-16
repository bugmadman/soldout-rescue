<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Sandbox;

use App\Domain\SoldOut\SoldOutCase;
use App\Domain\SoldOut\SoldOutCases;
use App\Infrastructure\Sandbox\SandboxClientInterface;

final readonly class FakeSandboxClient implements SandboxClientInterface
{
    /**
     * @var list<SoldOutCase>
     */
    private array $cases;

    public function __construct(SoldOutCase ...$cases)
    {
        $this->cases = array_values($cases);
    }

    public function soldOutsOn(\DateTimeImmutable $date): SoldOutCases
    {
        $day = $date->format('Y-m-d');

        return new SoldOutCases(...array_filter(
            $this->cases,
            static fn (SoldOutCase $case): bool => $case->soldOut->soldOutDate->format('Y-m-d') === $day,
        ));
    }

    public function latestSoldOutDate(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->cases as $case) {
            if ($latest === null || $case->soldOut->soldOutDate > $latest) {
                $latest = $case->soldOut->soldOutDate;
            }
        }

        return $latest;
    }
}
