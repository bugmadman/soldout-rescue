<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Money\HistoricalEstimator;
use App\Infrastructure\DataPack\DataPackDirectory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:estimate-history',
    description: 'Writes historical estimates per size band from a data pack to a JSON file',
)]
final readonly class EstimateHistoryCommand
{
    public function __construct(
        private HistoricalEstimator $estimator,
        #[Autowire('%kernel.project_dir%/config/historical_estimates.json')]
        private string $defaultOutput,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Directory with the data pack CSV files')]
        string $dataPackDir,
        #[Argument(description: 'Output JSON file, config/historical_estimates.json by default')]
        ?string $outputJson = null,
    ): int {
        $output = $outputJson ?? $this->defaultOutput;
        $outputDir = \dirname($output);
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            $io->error(\sprintf('Output directory "%s" does not exist or is not writable.', $outputDir));

            return Command::FAILURE;
        }

        try {
            $estimates = $this->estimator->estimate(DataPackDirectory::fromPath($dataPackDir));
        } catch (\DomainException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $json = json_encode($estimates->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        if (!$this->writeAtomically($output, $json."\n")) {
            $io->error(\sprintf('Cannot write "%s".', $output));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Estimates from %d sold-outs written to %s.', $estimates->soldOuts, $output));

        return Command::SUCCESS;
    }

    private function writeAtomically(string $path, string $content): bool
    {
        $temp = \sprintf('%s/.%s.%s.tmp', \dirname($path), basename($path), bin2hex(random_bytes(6)));
        if (@file_put_contents($temp, $content) === \strlen($content) && @rename($temp, $path)) {
            return true;
        }
        if (is_file($temp)) {
            unlink($temp);
        }

        return false;
    }
}
