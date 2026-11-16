<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

enum Recommendation: string
{
    case Call = 'call';
    case DontCall = 'dont_call';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::DontCall => "Don't call",
        };
    }
}
