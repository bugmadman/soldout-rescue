<?php

declare(strict_types=1);

namespace App\Domain\Money;

enum ActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Note = 'note';
}
