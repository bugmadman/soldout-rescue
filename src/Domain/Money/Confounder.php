<?php

declare(strict_types=1);

namespace App\Domain\Money;

enum Confounder: string
{
    case AutoReplenishSchedule = 'auto_replenish_schedule';
    case DealAlertSend = 'deal_alert_send';
}
