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
use App\Domain\Money\SoldOutRecord;

final readonly class CsvDataPackReader implements DataPackReaderInterface
{
    private const string SOLD_OUTS = 'sold_out_deals.csv';
    private const string ACTIVITIES = 'activities.csv';
    private const string MERCHANTS = 'merchants.csv';
    private const string MERCHANT_MONTHS = 'merchant_months.csv';
    private const string SCHEDULES = 'auto_replenish_schedules.csv';
    private const string DEAL_ALERT_SENDS = 'deal_alert_sends.csv';

    public function read(DataPackDirectory $directory): HistoricalRecords
    {
        $merchantIds = [];

        return new HistoricalRecords(
            $this->records(
                $directory,
                self::SOLD_OUTS,
                ['sold_out_id', 'deal_id', 'merchant_id', 'sold_out_date', 'restocked_date', 'gp_30d_after_gbp'],
                fn (array $row): SoldOutRecord => new SoldOutRecord(
                    $row['sold_out_id'],
                    $row['deal_id'],
                    $row['merchant_id'],
                    $this->date($row['sold_out_date']),
                    $row['restocked_date'] === '' ? null : $this->date($row['restocked_date']),
                    $row['gp_30d_after_gbp'] === '' ? null : Money::fromPounds($row['gp_30d_after_gbp']),
                ),
            ),
            $this->records(
                $directory,
                self::ACTIVITIES,
                ['merchant_id', 'sold_out_id', 'activity_date', 'type', 'owner_id', 'duration_s'],
                fn (array $row): ActivityRecord => new ActivityRecord(
                    $row['merchant_id'],
                    $row['sold_out_id'] === '' ? null : $row['sold_out_id'],
                    $this->date($row['activity_date']),
                    ActivityType::tryFrom($row['type'])
                        ?? throw new \DomainException(\sprintf('unknown activity type "%s".', $row['type'])),
                    $row['owner_id'],
                    $row['duration_s'],
                ),
            ),
            $this->records(
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
            $this->records(
                $directory,
                self::MERCHANT_MONTHS,
                ['merchant_id', 'gp_gbp', 'refunds_gbp'],
                static fn (array $row): MerchantMonthRecord => new MerchantMonthRecord(
                    $row['merchant_id'],
                    Money::fromPounds($row['gp_gbp']),
                    Money::fromPounds($row['refunds_gbp']),
                ),
            ),
            !$directory->has(self::SCHEDULES) ? null : $this->records(
                $directory,
                self::SCHEDULES,
                ['deal_id', 'start_date', 'end_date'],
                fn (array $row): ScheduleRecord => new ScheduleRecord(
                    $row['deal_id'],
                    $this->date($row['start_date']),
                    $row['end_date'] === '' ? null : $this->date($row['end_date']),
                ),
            ),
            !$directory->has(self::DEAL_ALERT_SENDS) ? null : $this->records(
                $directory,
                self::DEAL_ALERT_SENDS,
                ['deal_id', 'sent_date'],
                fn (array $row): DealAlertSendRecord => new DealAlertSendRecord(
                    $row['deal_id'],
                    $this->date($row['sent_date']),
                ),
            ),
        );
    }

    /**
     * @template T
     *
     * @param list<string>                       $columns
     * @param \Closure(array<string, string>): T $map
     *
     * @return list<T>
     */
    private function records(DataPackDirectory $directory, string $name, array $columns, \Closure $map): array
    {
        if (!$directory->has($name)) {
            throw new \RuntimeException(\sprintf('Data pack file "%s" is missing.', $name));
        }

        try {
            $file = new \SplFileObject($directory->file($name), 'r');
        } catch (\RuntimeException|\LogicException $e) {
            throw new \RuntimeException(\sprintf('Data pack file "%s" cannot be read.', $name), 0, $e);
        }

        $header = null;
        $records = [];
        $record = 0;
        while (\is_array($fields = $file->fgetcsv(',', '"', ''))) {
            if ($fields === [null]) {
                continue;
            }
            $fields = array_map(static fn (?string $field): string => trim((string) $field), $fields);

            if ($header === null) {
                $header = $this->header($name, $fields, $columns);
                continue;
            }

            ++$record;
            if (\count($fields) !== \count($header)) {
                $message = \sprintf(
                    '%s, record %d: %d fields, expected %d.',
                    $name,
                    $record,
                    \count($fields),
                    \count($header),
                );
                throw new \DomainException($message);
            }

            try {
                $records[] = $map(array_combine($header, $fields));
            } catch (\DomainException $e) {
                throw new \DomainException(\sprintf('%s, record %d: %s', $name, $record, $e->getMessage()), 0, $e);
            }
        }

        if ($header === null) {
            throw new \DomainException(\sprintf('%s has no header row.', $name));
        }

        return $records;
    }

    /**
     * @param list<string> $fields
     * @param list<string> $columns
     *
     * @return list<string>
     */
    private function header(string $name, array $fields, array $columns): array
    {
        $fields[0] = preg_replace('/^\x{FEFF}/u', '', $fields[0]) ?? $fields[0];
        $missing = array_diff($columns, $fields);
        if ($missing !== []) {
            throw new \DomainException(\sprintf('%s lacks columns: %s.', $name, implode(', ', $missing)));
        }

        return $fields;
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \DomainException(\sprintf('"%s" is not a Y-m-d date.', $value));
        }

        return $date;
    }
}
