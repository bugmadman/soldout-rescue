<?php

declare(strict_types=1);

namespace App\Infrastructure\Sandbox;

use App\Domain\SoldOut\SoldOutCases;

interface SandboxClientInterface
{
    public function soldOutsOn(\DateTimeImmutable $date): SoldOutCases;
}
