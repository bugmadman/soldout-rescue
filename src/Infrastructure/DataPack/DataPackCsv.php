<?php

declare(strict_types=1);

namespace App\Infrastructure\DataPack;

use App\Domain\Money\Money;
use App\Domain\Money\SoldOutRecord;

final readonly class DataPackCsv
{
    private const string SOLD_OUTS = 'sold_out_deals.csv';

    /**
     * @return list<SoldOutRecord>
     */
    public function soldOuts(DataPackDirectory $directory): array
    {
        return $this->records(
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
    public function records(DataPackDirectory $directory, string $name, array $columns, \Closure $map): array
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

    public function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \DomainException(\sprintf('"%s" is not a Y-m-d date.', $value));
        }

        return $date;
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
}
