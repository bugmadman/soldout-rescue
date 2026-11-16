<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Estimates;

use App\Domain\Money\Money;
use App\Domain\Money\MoneyEstimate;
use App\Domain\Money\SizeBand;
use App\Domain\SoldOut\CallGainEstimates;
use App\Infrastructure\Estimates\HistoricalEstimatesFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HistoricalEstimatesFile::class)]
#[UsesClass(CallGainEstimates::class)]
#[UsesClass(Money::class)]
#[UsesClass(MoneyEstimate::class)]
final class HistoricalEstimatesFileTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testReadsTheAllCutGainOfEachBand(): void
    {
        $gains = $this->read(json_encode([
            'bands' => [
                'A' => [
                    'all' => [
                        'gp_30d_after_difference_pence' => ['central' => 496, 'high' => 692, 'low' => -3, 'se' => 100],
                    ],
                    'unconfounded' => [
                        'gp_30d_after_difference_pence' => ['central' => 1, 'high' => 2, 'low' => 0],
                    ],
                ],
                'C' => ['all' => ['gp_30d_after_difference_pence' => ['central' => null, 'low' => 5, 'high' => 9]]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $a = $gains->forBand(SizeBand::A);
        self::assertSame(496, $a?->central?->toPence());
        self::assertSame(100, $a->standardError?->toPence());
        self::assertSame(-3, $a->low?->toPence());
        self::assertSame(692, $a->high?->toPence());
        self::assertNull($gains->forBand(SizeBand::B));
        self::assertNull($gains->forBand(SizeBand::C)?->central);
        self::assertSame(5, $gains->forBand(SizeBand::C)?->low?->toPence());
        self::assertNull($gains->forBand(SizeBand::D));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedFiles(): iterable
    {
        yield 'not JSON' => ['{', 'is malformed: it is not valid JSON.'];
        yield 'no bands' => ['{"counts": {}}', 'is malformed: it has no "bands" object.'];
        yield 'gain not an object' => [
            '{"bands": {"B": {"all": {"gp_30d_after_difference_pence": 12}}}}',
            'is malformed: the gain of size band B is not an object.',
        ];
        yield 'fractional pence' => [
            '{"bands": {"D": {"all": {"gp_30d_after_difference_pence": {"central": 1.5}}}}}',
            'is malformed: "central" of size band D is not a whole number of pence.',
        ];
        yield 'pounds as a string' => [
            '{"bands": {"A": {"all": {"gp_30d_after_difference_pence": {"low": "4.96"}}}}}',
            'is malformed: "low" of size band A is not a whole number of pence.',
        ];
    }

    #[DataProvider('malformedFiles')]
    public function testMalformedFileIsAnEnvironmentFailure(string $json, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->read($json);
    }

    public function testMissingFileIsAnEnvironmentFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Historical estimates file "absent.json" is missing.');

        new HistoricalEstimatesFile(sys_get_temp_dir().'/absent.json')->callGainEstimates();
    }

    private function read(string $json): CallGainEstimates
    {
        $this->file = sys_get_temp_dir().'/estimates-'.bin2hex(random_bytes(6)).'.json';
        file_put_contents($this->file, $json);

        return new HistoricalEstimatesFile($this->file)->callGainEstimates();
    }
}
