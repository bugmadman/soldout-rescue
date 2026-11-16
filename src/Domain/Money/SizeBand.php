<?php

declare(strict_types=1);

namespace App\Domain\Money;

enum SizeBand: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
}
