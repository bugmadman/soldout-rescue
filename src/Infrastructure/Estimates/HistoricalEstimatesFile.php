<?php

declare(strict_types=1);

namespace App\Infrastructure\Estimates;

use App\Domain\Money\Money;
use App\Domain\Money\MoneyEstimate;
use App\Domain\Money\SizeBand;
use App\Domain\SoldOut\CallGainEstimates;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class HistoricalEstimatesFile
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/historical_estimates.json')]
        private string $path,
    ) {
    }

    public function callGainEstimates(): CallGainEstimates
    {
        $bands = self::at($this->decode(), 'bands');
        if (!\is_array($bands)) {
            throw $this->malformed('it has no "bands" object');
        }

        $estimates = CallGainEstimates::none();
        foreach (SizeBand::cases() as $band) {
            $gain = self::at($bands, $band->value, 'all', 'gp_30d_after_difference_pence');
            if ($gain === null) {
                continue;
            }
            if (!\is_array($gain)) {
                throw $this->malformed(\sprintf('the gain of size band %s is not an object', $band->value));
            }
            $estimates = $estimates->with($band, new MoneyEstimate(
                $this->pence($gain, 'central', $band),
                $this->pence($gain, 'se', $band),
                $this->pence($gain, 'low', $band),
                $this->pence($gain, 'high', $band),
            ));
        }

        return $estimates;
    }

    private function decode(): mixed
    {
        if (!is_file($this->path)) {
            throw new \RuntimeException(\sprintf('Historical estimates file "%s" is missing.', basename($this->path)));
        }
        $json = @file_get_contents($this->path);
        if ($json === false) {
            throw new \RuntimeException(\sprintf('Historical estimates file "%s" cannot be read.', basename($this->path)));
        }

        try {
            return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw $this->malformed('it is not valid JSON', $e);
        }
    }

    /**
     * @param array<mixed> $gain
     */
    private function pence(array $gain, string $key, SizeBand $band): ?Money
    {
        $value = $gain[$key] ?? null;
        if ($value !== null && !\is_int($value)) {
            throw $this->malformed(\sprintf('"%s" of size band %s is not a whole number of pence', $key, $band->value));
        }

        return $value === null ? null : Money::fromPence($value);
    }

    private static function at(mixed $value, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($value)) {
                return null;
            }
            $value = $value[$key] ?? null;
        }

        return $value;
    }

    private function malformed(string $problem, ?\Throwable $previous = null): \RuntimeException
    {
        return new \RuntimeException(
            \sprintf('Historical estimates file "%s" is malformed: %s.', basename($this->path), $problem),
            0,
            $previous,
        );
    }
}
