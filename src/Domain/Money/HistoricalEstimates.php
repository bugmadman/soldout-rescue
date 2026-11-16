<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class HistoricalEstimates
{
    /**
     * @param list<BandEstimates> $bands
     * @param list<Confounder>    $unconfoundedExcludes
     * @param list<string>        $notes
     */
    public function __construct(
        public array $bands,
        public array $unconfoundedExcludes,
        public int $activityRows,
        public int $activityDuplicateRows,
        public int $soldOuts,
        public int $soldOutsWithoutBand,
        public int $soldOutsWithSchedule,
        public int $soldOutsWithDealAlert,
        public int $merchantMonths,
        public array $notes,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $bands = [];
        foreach ($this->bands as $band) {
            $bands[$band->band->value] = $band->toArray();
        }

        return self::sortKeys([
            'bands' => $bands,
            'unconfounded_excludes' => array_map(
                static fn (Confounder $confounder): string => $confounder->value,
                $this->unconfoundedExcludes,
            ),
            'counts' => [
                'activity_rows' => $this->activityRows,
                'activity_duplicate_rows' => $this->activityDuplicateRows,
                'sold_outs' => $this->soldOuts,
                'sold_outs_without_band' => $this->soldOutsWithoutBand,
                'sold_outs_with_schedule' => $this->soldOutsWithSchedule,
                'sold_outs_with_deal_alert' => $this->soldOutsWithDealAlert,
                'merchant_months' => $this->merchantMonths,
            ],
            'notes' => $this->notes,
        ]);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }
        if (!array_is_list($value)) {
            ksort($value, \SORT_STRING);
        }

        return $value;
    }
}
