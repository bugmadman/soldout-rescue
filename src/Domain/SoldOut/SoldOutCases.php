<?php

declare(strict_types=1);

namespace App\Domain\SoldOut;

/**
 * @implements \IteratorAggregate<int, SoldOutCase>
 */
final readonly class SoldOutCases implements \IteratorAggregate, \Countable
{
    /**
     * @var list<SoldOutCase>
     */
    private array $cases;

    public function __construct(SoldOutCase ...$cases)
    {
        $this->cases = array_values($cases);
    }

    /**
     * @return \ArrayIterator<int, SoldOutCase>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->cases);
    }

    public function count(): int
    {
        return \count($this->cases);
    }
}
