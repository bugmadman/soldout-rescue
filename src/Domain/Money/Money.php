<?php

declare(strict_types=1);

namespace App\Domain\Money;

final readonly class Money
{
    private const string DECIMAL_PATTERN = '/^(-?)(\d+)(?:\.(\d+))?$/D';
    private const int MAX_DIGITS = 18;
    // pence * numerator must fit an int, so a factor keeps 9 digits: amounts up to about £92m stay exact
    private const int MAX_FACTOR_DIGITS = 9;

    private function __construct(
        private int $pence,
    ) {
    }

    public static function fromPence(int $pence): self
    {
        return new self($pence);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromPounds(string $pounds): self
    {
        [$negative, $whole, $fraction] = self::parseDecimal($pounds);

        $fraction = str_pad($fraction, 3, '0');
        $magnitude = self::checked(
            self::checked(self::digitsToInt($whole) * 100)
            + (int) substr($fraction, 0, 2)
            + ((int) $fraction[2] >= 5 ? 1 : 0),
        );

        return new self($negative ? -$magnitude : $magnitude);
    }

    public function toPence(): int
    {
        return $this->pence;
    }

    public function add(self $other): self
    {
        return new self(self::checked($this->pence + $other->pence));
    }

    public function subtract(self $other): self
    {
        return new self(self::checked($this->pence - $other->pence));
    }

    public function negate(): self
    {
        return new self(self::checked(-$this->pence));
    }

    public function multiplyBy(string $factor, RoundingMode $rounding): self
    {
        [$negative, $whole, $fraction] = self::parseDecimal($factor);

        $digits = ltrim($whole.$fraction, '0');
        if (\strlen($fraction) > self::MAX_FACTOR_DIGITS || \strlen($digits) > self::MAX_FACTOR_DIGITS) {
            throw new \DomainException(\sprintf('Factor "%s" has more than 9 digits.', $factor));
        }

        $numerator = (int) $digits;
        $denominator = (int) ('1'.str_repeat('0', \strlen($fraction)));
        $pence = $rounding->divide(
            self::checked($this->pence * ($negative ? -$numerator : $numerator)),
            $denominator,
        );

        return new self($pence);
    }

    public function compareTo(self $other): int
    {
        return $this->pence <=> $other->pence;
    }

    public function isNegative(): bool
    {
        return $this->pence < 0;
    }

    public function format(): string
    {
        return \sprintf(
            '%s£%d.%02d',
            $this->isNegative() ? '-' : '',
            abs(intdiv($this->pence, 100)),
            abs($this->pence % 100),
        );
    }

    /**
     * @return array{bool, string, string}
     */
    private static function parseDecimal(string $value): array
    {
        if (preg_match(self::DECIMAL_PATTERN, trim($value), $matches) !== 1) {
            throw new \DomainException(\sprintf('"%s" is not a decimal number.', $value));
        }

        return [$matches[1] === '-', $matches[2], $matches[3] ?? ''];
    }

    private static function digitsToInt(string $digits): int
    {
        $significant = ltrim($digits, '0');
        if (\strlen($significant) > self::MAX_DIGITS) {
            throw new \DomainException(\sprintf('"%s" is too large for a money amount.', $digits));
        }

        return (int) $significant;
    }

    private static function checked(int|float $result): int
    {
        if (!\is_int($result)) {
            throw new \DomainException('Money amount overflows the integer range.');
        }

        return $result;
    }
}
