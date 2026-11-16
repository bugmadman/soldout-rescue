<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class HistoricalRecords
{
    /**
     * @param list<SoldOutRecord>            $soldOuts
     * @param list<ActivityRecord>           $activities
     * @param list<MerchantRecord>           $merchants
     * @param list<MerchantMonthRecord>      $merchantMonths
     * @param list<ScheduleRecord>|null      $schedules      null when the data pack has no schedules file
     * @param list<DealAlertSendRecord>|null $dealAlertSends null when the data pack has no Deal Alert sends file
     */
    public function __construct(
        public array $soldOuts,
        public array $activities,
        public array $merchants,
        public array $merchantMonths,
        public ?array $schedules,
        public ?array $dealAlertSends,
    ) {
    }
}
