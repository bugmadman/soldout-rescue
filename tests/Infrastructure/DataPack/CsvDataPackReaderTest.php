<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\DataPack;

use App\Domain\Money\ActivityType;
use App\Domain\Money\SizeBand;
use App\Infrastructure\DataPack\CsvDataPackReader;
use App\Infrastructure\DataPack\DataPackDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvDataPackReader::class)]
#[CoversClass(DataPackDirectory::class)]
final class CsvDataPackReaderTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/data-pack';

    private ?string $copy = null;

    protected function tearDown(): void
    {
        if ($this->copy !== null) {
            foreach ($this->csvFiles($this->copy) as $file) {
                unlink($file);
            }
            rmdir($this->copy);
        }
    }

    public function testReadsTheFixtureDataPack(): void
    {
        $records = new CsvDataPackReader()->read(DataPackDirectory::fromPath(self::FIXTURES.'/'));

        self::assertCount(5, $records->soldOuts);
        self::assertCount(5, $records->activities);
        self::assertCount(4, $records->merchants);
        self::assertCount(4, $records->merchantMonths);
        self::assertCount(2, $records->schedules ?? []);
        self::assertCount(3, $records->dealAlertSends ?? []);

        $first = $records->soldOuts[0];
        self::assertSame('SO-900001', $first->soldOutId);
        self::assertSame('D90001-1', $first->dealId);
        self::assertSame('2025-09-01', $first->soldOutDate->format('Y-m-d'));
        self::assertSame('2025-09-10', $first->restockedDate?->format('Y-m-d'));
        self::assertSame(12000, $first->gp30dAfter?->toPence());
        self::assertNull($records->soldOuts[1]->restockedDate);
        self::assertSame(1550, $records->soldOuts[1]->gp30dAfter?->toPence());
        self::assertNull($records->soldOuts[4]->gp30dAfter);

        self::assertSame('2025-09-02', $records->activities[0]->activityDate->format('Y-m-d'));
        self::assertSame(ActivityType::Call, $records->activities[0]->type);
        self::assertSame(ActivityType::Note, $records->activities[3]->type);
        self::assertSame(ActivityType::Email, $records->activities[4]->type);
        self::assertNull($records->activities[3]->soldOutId);
        self::assertSame(SizeBand::C, $records->merchants[2]->sizeBand);
        self::assertSame(3000, $records->merchantMonths[0]->refunds->toPence());
        $schedules = $records->schedules ?? [];
        self::assertCount(2, $schedules);
        self::assertNull($schedules[0]->endDate);
        self::assertSame('2025-08-31', $schedules[1]->endDate?->format('Y-m-d'));
    }

    public function testOptionalFilesMayBeAbsent(): void
    {
        $directory = $this->copyFixtures(['auto_replenish_schedules.csv', 'deal_alert_sends.csv']);

        $records = new CsvDataPackReader()->read($directory);

        self::assertNull($records->schedules);
        self::assertNull($records->dealAlertSends);
    }

    public function testMissingRequiredFileIsAnEnvironmentFailure(): void
    {
        $directory = $this->copyFixtures(['activities.csv']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"activities.csv" is missing');

        new CsvDataPackReader()->read($directory);
    }

    public function testMissingColumnIsRejected(): void
    {
        $directory = $this->copyFixtures(write: ['merchants.csv' => "merchant_id,region\nM1,North\n"]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('merchants.csv lacks columns: size_band.');

        new CsvDataPackReader()->read($directory);
    }

    public function testWrongFieldCountNamesTheRecord(): void
    {
        $directory = $this->copyFixtures(write: ['merchants.csv' => "merchant_id,size_band\nM1,A\nM2\n"]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('merchants.csv, record 2: 1 fields, expected 2.');

        new CsvDataPackReader()->read($directory);
    }

    public function testMalformedDateNamesTheRecord(): void
    {
        $directory = $this->copyFixtures(write: [
            'deal_alert_sends.csv' => "deal_id,sent_date\nD1,2025-09-01\nD2,01/09/2025\n",
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('deal_alert_sends.csv, record 2: "01/09/2025" is not a Y-m-d date.');

        new CsvDataPackReader()->read($directory);
    }

    public function testMalformedAmountIsRejected(): void
    {
        $directory = $this->copyFixtures(write: [
            'merchant_months.csv' => "merchant_id,gp_gbp,refunds_gbp\nM1,12.00,n/a\n",
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('merchant_months.csv, record 1:');

        new CsvDataPackReader()->read($directory);
    }

    public function testByteOrderMarkBlankLinesAndPaddingAreHandled(): void
    {
        $directory = $this->copyFixtures(write: [
            'merchants.csv' => "\u{FEFF}merchant_id,size_band\n\nM1, B \nM2,D\n",
        ]);

        $merchants = new CsvDataPackReader()->read($directory)->merchants;

        self::assertCount(2, $merchants);
        self::assertSame(SizeBand::B, $merchants[0]->sizeBand);
        self::assertSame(SizeBand::D, $merchants[1]->sizeBand);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidRecords(): iterable
    {
        yield 'unknown size band' => [
            'merchants.csv',
            "merchant_id,size_band\nM1,A\nM2,Z\n",
            'merchants.csv, record 2: unknown size_band "Z".',
        ];
        yield 'blank size band' => [
            'merchants.csv',
            "merchant_id,size_band\nM1,\n",
            'merchants.csv, record 1: unknown size_band "".',
        ];
        yield 'duplicate merchant' => [
            'merchants.csv',
            "merchant_id,size_band\nM1,A\nM2,B\nM1,C\n",
            'merchants.csv, record 3: duplicate merchant_id "M1".',
        ];
        yield 'unknown activity type' => [
            'activities.csv',
            "merchant_id,sold_out_id,activity_date,type,owner_id,duration_s\nM1,S1,2025-09-01,sms,R1,\n",
            'activities.csv, record 1: unknown activity type "sms".',
        ];
        yield 'malformed restocked date' => [
            'sold_out_deals.csv',
            "sold_out_id,deal_id,merchant_id,sold_out_date,restocked_date,gp_30d_after_gbp\n"
                ."S1,D1,M1,2025-09-01,soon,\n",
            'sold_out_deals.csv, record 1: "soon" is not a Y-m-d date.',
        ];
    }

    #[DataProvider('invalidRecords')]
    public function testInvalidRecordIsRejectedWithFileAndRecord(string $file, string $content, string $message): void
    {
        $directory = $this->copyFixtures(write: [$file => $content]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($message);

        new CsvDataPackReader()->read($directory);
    }

    public function testMissingDirectoryIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        DataPackDirectory::fromPath(self::FIXTURES.'/does-not-exist');
    }

    /**
     * @return list<string>
     */
    private function csvFiles(string $directory): array
    {
        $files = glob($directory.'/*.csv');

        return $files === false ? [] : $files;
    }

    /**
     * @param list<string>          $omit
     * @param array<string, string> $write
     */
    private function copyFixtures(array $omit = [], array $write = []): DataPackDirectory
    {
        $this->copy = sys_get_temp_dir().'/data-pack-'.bin2hex(random_bytes(6));
        mkdir($this->copy);
        foreach ($this->csvFiles(self::FIXTURES) as $file) {
            if (!\in_array(basename($file), $omit, true)) {
                copy($file, $this->copy.'/'.basename($file));
            }
        }
        foreach ($write as $name => $content) {
            file_put_contents($this->copy.'/'.$name, $content);
        }

        return DataPackDirectory::fromPath($this->copy);
    }
}
