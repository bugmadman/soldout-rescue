<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

/**
 * @implements \IteratorAggregate<int, RecommendedSoldOut>
 */
final readonly class RecommendedSoldOuts implements \IteratorAggregate, \Countable
{
    /**
     * @var list<RecommendedSoldOut>
     */
    private array $items;

    public function __construct(RecommendedSoldOut ...$items)
    {
        $items = array_values($items);
        usort($items, self::rank(...));
        $this->items = $items;
    }

    /**
     * @return \ArrayIterator<int, RecommendedSoldOut>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    private static function rank(RecommendedSoldOut $a, RecommendedSoldOut $b): int
    {
        $aCalls = $a->recommendation === Recommendation::Call;
        $bCalls = $b->recommendation === Recommendation::Call;
        if ($aCalls !== $bCalls) {
            return $aCalls ? -1 : 1;
        }
        if ($aCalls) {
            $byNet = $b->netCentral->compareTo($a->netCentral);
            if ($byNet !== 0) {
                return $byNet;
            }
        }

        return strcmp($a->case->soldOut->soldOutId, $b->case->soldOut->soldOutId);
    }
}
