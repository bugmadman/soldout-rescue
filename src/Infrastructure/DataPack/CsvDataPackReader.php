<?php

declare(strict_types=1);

namespace App\Infrastructure\DataPack;

use App\Domain\Money\ActivityRecord;
use App\Domain\Money\ActivityType;
use App\Domain\Money\DealAlertSendRecord;
use App\Domain\Money\HistoricalRecords;
use App\Domain\Money\MerchantMonthRecord;
use App\Domain\Money\MerchantRecord;
use App\Domain\Money\Money;
use App\Domain\Money\ScheduleRecord;
use App\Domain\Money\SizeBand;

final readonly class CsvDataPackReader implements DataPackReaderInterface
{
    private const string ACTIVITIES = 'activities.csv';
    private const string MERCHANTS = 'merchants.csv';
    private const string MERCHANT_MONTHS = 'merchant_months.csv';
    private const string SCHEDULES = 'auto_replenish_schedules.csv';
    private const string DEAL_ALERT_SENDS = 'deal_alert_sends.csv';

    public function __construct(
        private DataPackCsv $csv = new DataPackCsv(),
    ) {
    }

    public function read(DataPackDirectory $directory): HistoricalRecords
    {
        $merchantIds = [];

        return new HistoricalRecords(
            $this->csv->soldOuts($directory),
            $this->csv->records(
                $directory,
                self::ACTIVITIES,
                ['merchant_id', 'sold_out_id', 'activity_date', 'type', 'owner_id', 'duration_s'],
                fn (array $row): ActivityRecord => new ActivityRecord(
                    $row['merchant_id'],
                    $row['sold_out_id'] === '' ? null : $row['sold_out_id'],
                    $this->csv->date($row['activity_date']),
                    ActivityType::tryFrom($row['type'])
                        ?? throw new \DomainException(\sprintf('unknown activity type "%s".', $row['type'])),
                    $row['owner_id'],
                    $row['duration_s'],
                ),
            ),
            $this->csv->records(
                $directory,
                self::MERCHANTS,
                ['merchant_id', 'size_band'],
                static function (array $row) use (&$merchantIds): MerchantRecord {
                    if (isset($merchantIds[$row['merchant_id']])) {
                        throw new \DomainException(\sprintf('duplicate merchant_id "%s".', $row['merchant_id']));
                    }
                    $merchantIds[$row['merchant_id']] = true;

                    return new MerchantRecord(
                        $row['merchant_id'],
                        SizeBand::tryFrom($row['size_band'])
                            ?? throw new \DomainException(\sprintf('unknown size_band "%s".', $row['size_band'])),
                    );
                },
            ),
            $this->csv->records(
                $directory,
                self::MERCHANT_MONTHS,
                ['merchant_id', 'gp_gbp', 'refunds_gbp'],
                static fn (array $row): MerchantMonthRecord => new MerchantMonthRecord(
                    $row['merchant_id'],
                    Money::fromPounds($row['gp_gbp']),
                    Money::fromPounds($row['refunds_gbp']),
                ),
            ),
            !$directory->has(self::SCHEDULES) ? null : $this->csv->records(
                $directory,
                self::SCHEDULES,
                ['deal_id', 'start_date', 'end_date'],
                fn (array $row): ScheduleRecord => new ScheduleRecord(
                    $row['deal_id'],
                    $this->csv->date($row['start_date']),
                    $row['end_date'] === '' ? null : $this->csv->date($row['end_date']),
                ),
            ),
            !$directory->has(self::DEAL_ALERT_SENDS) ? null : $this->csv->records(
                $directory,
                self::DEAL_ALERT_SENDS,
                ['deal_id', 'sent_date'],
                fn (array $row): DealAlertSendRecord => new DealAlertSendRecord(
                    $row['deal_id'],
                    $this->csv->date($row['sent_date']),
                ),
            ),
        );
    }
}
