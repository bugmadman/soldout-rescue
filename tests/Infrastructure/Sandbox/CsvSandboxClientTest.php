<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Sandbox;

use App\Domain\Money\SizeBand;
use App\Domain\SoldOut\SoldOutCase;
use App\Domain\SoldOut\SoldOutCases;
use App\Infrastructure\DataPack\DataPackCsv;
use App\Infrastructure\DataPack\DataPackDirectory;
use App\Infrastructure\Sandbox\CsvSandboxClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvSandboxClient::class)]
#[CoversClass(DataPackCsv::class)]
#[UsesClass(DataPackDirectory::class)]
#[UsesClass(SoldOutCase::class)]
#[UsesClass(SoldOutCases::class)]
final class CsvSandboxClientTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/data-pack';
    private const string MERCHANTS_HEADER = "merchant_id,merchant_name,city,size_band,rep_id,do_not_contact\n";
    private const string SOLD_OUTS_HEADER = 'sold_out_id,deal_id,merchant_id,sold_out_date,'
        ."restocked_date,gp_30d_after_gbp\n";

    private ?string $copy = null;

    protected function tearDown(): void
    {
        if ($this->copy !== null) {
            $files = glob($this->copy.'/*.csv');
            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }
            rmdir($this->copy);
        }
    }

    public function testReturnsOnlyTheSoldOutsOfThatDayJoinedToTheirMerchant(): void
    {
        $cases = $this->client(DataPackDirectory::fromPath(self::FIXTURES))
            ->soldOutsOn(new \DateTimeImmutable('2025-09-04 17:45:00'));

        self::assertCount(1, $cases);
        $case = $this->only($cases);
        self::assertSame('SO-900004', $case->soldOut->soldOutId);
        self::assertSame('D90003-1', $case->soldOut->dealId);
        self::assertSame(500, $case->soldOut->gp30dAfter?->toPence());
        self::assertSame('M90003', $case->merchant->merchantId);
        self::assertSame(SizeBand::C, $case->merchant->sizeBand);
        self::assertSame('Fixture Gym', $case->merchantName);
        self::assertSame('Bristol', $case->city);
        self::assertSame('R91', $case->repId);
        self::assertTrue($case->doNotContact);
    }

    public function testDoNotContactZeroMeansContactable(): void
    {
        $case = $this->only(
            $this->client(DataPackDirectory::fromPath(self::FIXTURES))
                ->soldOutsOn(new \DateTimeImmutable('2025-09-01')),
        );

        self::assertSame('SO-900001', $case->soldOut->soldOutId);
        self::assertSame('Fixture Spa', $case->merchantName);
        self::assertSame(SizeBand::A, $case->merchant->sizeBand);
        self::assertFalse($case->doNotContact);
    }

    public function testDayWithoutSoldOutsGivesNoCases(): void
    {
        $cases = $this->client(DataPackDirectory::fromPath(self::FIXTURES))
            ->soldOutsOn(new \DateTimeImmutable('2025-09-05'));

        self::assertCount(0, $cases);
    }

    public function testCasesAreOrderedBySoldOutId(): void
    {
        $directory = $this->dataPack(
            self::SOLD_OUTS_HEADER
                ."SO-3,D3,M1,2025-09-01,,\n"
                ."SO-1,D1,M2,2025-09-01,,\n"
                ."SO-4,D4,M1,2025-09-02,,\n"
                ."SO-2,D2,M1,2025-09-01,,\n",
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,0\nM2,Two,York,B,R2,1\n",
        );

        $cases = $this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01'));

        $ids = [];
        foreach ($cases as $case) {
            $ids[] = $case->soldOut->soldOutId;
        }
        self::assertSame(['SO-1', 'SO-2', 'SO-3'], $ids);
    }

    public function testBlankRepIdIsNull(): void
    {
        $directory = $this->dataPack(
            self::SOLD_OUTS_HEADER."SO-1,D1,M1,2025-09-01,,\n",
            self::MERCHANTS_HEADER."M1,One,Leeds,A,,0\n",
        );

        $case = $this->only($this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01')));

        self::assertNull($case->repId);
    }

    public function testSoldOutWithoutItsMerchantIsADataError(): void
    {
        $directory = $this->dataPack(
            self::SOLD_OUTS_HEADER."SO-1,D1,M1,2025-09-01,,\nSO-2,D2,M9,2025-09-01,,\n",
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,0\n",
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Sold-out "SO-2" refers to merchant "M9", which is not in merchants.csv.');

        $this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidMerchants(): iterable
    {
        yield 'do_not_contact not a flag' => [
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,yes\n",
            'merchants.csv, record 1: do_not_contact "yes" is not 0 or 1.',
        ];
        yield 'blank do_not_contact' => [
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,\n",
            'merchants.csv, record 1: do_not_contact "" is not 0 or 1.',
        ];
        yield 'unknown size band' => [
            self::MERCHANTS_HEADER."M1,One,Leeds,Z,R1,0\n",
            'merchants.csv, record 1: unknown size_band "Z".',
        ];
        yield 'duplicate merchant' => [
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,0\nM1,Again,York,B,R2,0\n",
            'merchants.csv, record 2: duplicate merchant_id "M1".',
        ];
        yield 'missing columns' => [
            "merchant_id,size_band,rep_id\nM1,A,R1\n",
            'merchants.csv lacks columns: merchant_name, city, do_not_contact.',
        ];
    }

    #[DataProvider('invalidMerchants')]
    public function testInvalidMerchantsFileIsRejected(string $merchants, string $message): void
    {
        $directory = $this->dataPack(self::SOLD_OUTS_HEADER."SO-1,D1,M1,2025-09-01,,\n", $merchants);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($message);

        $this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01'));
    }

    public function testMissingSoldOutsColumnIsRejected(): void
    {
        $directory = $this->dataPack(
            "sold_out_id,merchant_id,sold_out_date\nSO-1,M1,2025-09-01\n",
            self::MERCHANTS_HEADER."M1,One,Leeds,A,R1,0\n",
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sold_out_deals.csv lacks columns: deal_id, restocked_date, gp_30d_after_gbp.');

        $this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01'));
    }

    public function testMissingMerchantsFileIsAnEnvironmentFailure(): void
    {
        $directory = $this->dataPack(self::SOLD_OUTS_HEADER."SO-1,D1,M1,2025-09-01,,\n", null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data pack file "merchants.csv" is missing.');

        $this->client($directory)->soldOutsOn(new \DateTimeImmutable('2025-09-01'));
    }

    public function testLatestSoldOutDateIsTheMaximumInTheFile(): void
    {
        $latest = $this->client(DataPackDirectory::fromPath(self::FIXTURES))->latestSoldOutDate();

        self::assertSame('2026-10-20', $latest?->format('Y-m-d'));
    }

    public function testLatestSoldOutDateDoesNotDependOnRowOrder(): void
    {
        $directory = $this->dataPack(
            self::SOLD_OUTS_HEADER."SO-1,D1,M1,2025-09-03,,\nSO-2,D2,M1,2025-09-07,,\nSO-3,D3,M1,2025-09-01,,\n",
            null,
        );

        self::assertSame('2025-09-07', $this->client($directory)->latestSoldOutDate()?->format('Y-m-d'));
    }

    public function testNoSoldOutsMeansNoLatestDate(): void
    {
        $directory = $this->dataPack(self::SOLD_OUTS_HEADER, null);

        self::assertNull($this->client($directory)->latestSoldOutDate());
    }

    private function client(DataPackDirectory $directory): CsvSandboxClient
    {
        return new CsvSandboxClient($directory);
    }

    private function only(SoldOutCases $cases): SoldOutCase
    {
        self::assertCount(1, $cases);
        foreach ($cases as $case) {
            return $case;
        }

        self::fail('No sold-out case.');
    }

    private function dataPack(string $soldOuts, ?string $merchants): DataPackDirectory
    {
        $this->copy = sys_get_temp_dir().'/sandbox-pack-'.bin2hex(random_bytes(6));
        mkdir($this->copy);
        file_put_contents($this->copy.'/sold_out_deals.csv', $soldOuts);
        if ($merchants !== null) {
            file_put_contents($this->copy.'/merchants.csv', $merchants);
        }

        return DataPackDirectory::fromPath($this->copy);
    }
}
