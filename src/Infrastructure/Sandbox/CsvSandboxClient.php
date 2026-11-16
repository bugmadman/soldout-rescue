<?php

declare(strict_types=1);

namespace App\Infrastructure\Sandbox;

use App\Domain\Money\MerchantRecord;
use App\Domain\Money\SizeBand;
use App\Domain\Money\SoldOutRecord;
use App\Domain\SoldOut\SoldOutCase;
use App\Domain\SoldOut\SoldOutCases;
use App\Infrastructure\DataPack\DataPackCsv;
use App\Infrastructure\DataPack\DataPackDirectory;

/**
 * @phpstan-type Merchant array{record: MerchantRecord, name: string, city: string, repId: ?string, doNotContact: bool}
 */
final readonly class CsvSandboxClient implements SandboxClientInterface
{
    private const string MERCHANTS = 'merchants.csv';

    public function __construct(
        private DataPackDirectory $directory,
        private DataPackCsv $csv = new DataPackCsv(),
    ) {
    }

    public function soldOutsOn(\DateTimeImmutable $date): SoldOutCases
    {
        $day = $date->format('Y-m-d');
        $soldOuts = array_values(array_filter(
            $this->csv->soldOuts($this->directory),
            static fn (SoldOutRecord $soldOut): bool => $soldOut->soldOutDate->format('Y-m-d') === $day,
        ));
        usort($soldOuts, static fn (SoldOutRecord $a, SoldOutRecord $b): int => strcmp($a->soldOutId, $b->soldOutId));

        $merchants = $this->merchants();

        return new SoldOutCases(...array_map(
            static function (SoldOutRecord $soldOut) use ($merchants): SoldOutCase {
                $merchant = $merchants[$soldOut->merchantId] ?? null;
                if ($merchant === null) {
                    $message = \sprintf(
                        'Sold-out "%s" refers to merchant "%s", which is not in %s.',
                        $soldOut->soldOutId,
                        $soldOut->merchantId,
                        self::MERCHANTS,
                    );
                    throw new \DomainException($message);
                }

                return new SoldOutCase(
                    $soldOut,
                    $merchant['record'],
                    $merchant['name'],
                    $merchant['city'],
                    $merchant['repId'],
                    $merchant['doNotContact'],
                );
            },
            $soldOuts,
        ));
    }

    public function latestSoldOutDate(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->csv->soldOuts($this->directory) as $soldOut) {
            if ($latest === null || $soldOut->soldOutDate > $latest) {
                $latest = $soldOut->soldOutDate;
            }
        }

        return $latest;
    }

    /**
     * @return array<string, Merchant>
     */
    private function merchants(): array
    {
        $seen = [];
        $records = $this->csv->records(
            $this->directory,
            self::MERCHANTS,
            ['merchant_id', 'merchant_name', 'city', 'size_band', 'rep_id', 'do_not_contact'],
            static function (array $row) use (&$seen): array {
                if (isset($seen[$row['merchant_id']])) {
                    throw new \DomainException(\sprintf('duplicate merchant_id "%s".', $row['merchant_id']));
                }
                $seen[$row['merchant_id']] = true;

                return [
                    'record' => new MerchantRecord(
                        $row['merchant_id'],
                        SizeBand::tryFrom($row['size_band'])
                            ?? throw new \DomainException(\sprintf('unknown size_band "%s".', $row['size_band'])),
                    ),
                    'name' => $row['merchant_name'],
                    'city' => $row['city'],
                    'repId' => $row['rep_id'] === '' ? null : $row['rep_id'],
                    'doNotContact' => match ($flag = $row['do_not_contact']) {
                        '1' => true,
                        '0' => false,
                        default => throw new \DomainException(\sprintf('do_not_contact "%s" is not 0 or 1.', $flag)),
                    },
                ];
            },
        );

        return array_combine(
            array_map(static fn (array $merchant): string => $merchant['record']->merchantId, $records),
            $records,
        );
    }
}
