<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\EstimateHistoryCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(EstimateHistoryCommand::class)]
final class EstimateHistoryCommandTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures/data-pack';

    private string $output;

    protected function setUp(): void
    {
        $this->output = sys_get_temp_dir().'/historical-estimates-'.bin2hex(random_bytes(6)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->output)) {
            unlink($this->output);
        }
        parent::tearDown();
    }

    public function testWritesEstimatesForTheFixtureDataPack(): void
    {
        $tester = $this->tester();

        $tester->execute(['data-pack-dir' => self::FIXTURES, 'output-json' => $this->output]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $json = (string) file_get_contents($this->output);
        self::assertStringEndsWith("}\n", $json);
        self::assertStringNotContainsString('M9000', $json);
        self::assertStringNotContainsString('SO-9000', $json);

        $estimates = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($estimates);
        self::assertSame(
            [
                'activity_duplicate_rows' => 1,
                'activity_rows' => 5,
                'merchant_months' => 4,
                'sold_outs' => 5,
                'sold_outs_with_deal_alert' => 1,
                'sold_outs_with_schedule' => 1,
                'sold_outs_without_band' => 0,
            ],
            $estimates['counts'],
        );
        self::assertSame(['auto_replenish_schedule', 'deal_alert_send'], $estimates['unconfounded_excludes']);

        $bands = $estimates['bands'];
        self::assertIsArray($bands);
        self::assertSame(1, $this->at($bands, 'A.all.called.sold_outs'));
        self::assertSame('1.0000', $this->at($bands, 'A.all.called.restock_rate.central'));
        self::assertSame(12000, $this->at($bands, 'A.all.called.gp_30d_after_pence.central'));
        self::assertSame(1550, $this->at($bands, 'A.all.left_alone.gp_30d_after_pence.central'));
        self::assertSame(10450, $this->at($bands, 'A.all.gp_30d_after_difference_pence.central'));
        self::assertSame(0, $this->at($bands, 'A.unconfounded.left_alone.sold_outs'));
        self::assertSame(1, $this->at($bands, 'B.all.called.sold_outs'));
        self::assertSame(1, $this->at($bands, 'C.all.left_alone.sold_outs'));
        self::assertSame(0, $this->at($bands, 'C.unconfounded.left_alone.sold_outs'));
        self::assertSame(1, $this->at($bands, 'D.unconfounded.left_alone.sold_outs'));
        self::assertSame(0, $this->at($bands, 'D.all.left_alone.gp_30d_after_pence.n'));
        self::assertSame('0.0566', $this->at($bands, 'A.refund_rate.central'));
        self::assertSame('0.0476', $this->at($bands, 'B.refund_rate.central'));
        self::assertSame('0.0000', $this->at($bands, 'C.refund_rate.central'));
        self::assertSame('0.0323', $this->at($bands, 'D.refund_rate.central'));
    }

    public function testOutputIsStableAcrossRuns(): void
    {
        $tester = $this->tester();

        $tester->execute(['data-pack-dir' => self::FIXTURES, 'output-json' => $this->output]);
        $first = file_get_contents($this->output);
        $tester->execute(['data-pack-dir' => self::FIXTURES, 'output-json' => $this->output]);

        self::assertSame($first, file_get_contents($this->output));
        self::assertSame([], glob(\dirname($this->output).'/.'.basename($this->output).'.*'));
    }

    public function testMissingOutputDirectoryFailsWithoutWriting(): void
    {
        $tester = $this->tester();
        $output = \dirname($this->output).'/no-such-dir-'.bin2hex(random_bytes(6)).'/estimates.json';

        $tester->execute(['data-pack-dir' => self::FIXTURES, 'output-json' => $output]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR] Output directory', $tester->getDisplay());
        self::assertFileDoesNotExist($output);
    }

    public function testMissingDirectoryFailsWithoutWriting(): void
    {
        $tester = $this->tester();

        $tester->execute(['data-pack-dir' => self::FIXTURES.'/does-not-exist', 'output-json' => $this->output]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR] Data pack directory', $tester->getDisplay());
        self::assertFileDoesNotExist($this->output);
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:estimate-history'));
    }

    /**
     * @param array<mixed> $data
     */
    private function at(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            self::assertIsArray($data);
            self::assertArrayHasKey($key, $data);
            $data = $data[$key];
        }

        return $data;
    }
}
