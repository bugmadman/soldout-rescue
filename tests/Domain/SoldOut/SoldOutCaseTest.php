<?php

declare(strict_types=1);

namespace App\Tests\Domain\SoldOut;

use App\Domain\Money\MerchantRecord;
use App\Domain\Money\SizeBand;
use App\Domain\Money\SoldOutRecord;
use App\Domain\SoldOut\SoldOutCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoldOutCase::class)]
final class SoldOutCaseTest extends TestCase
{
    public function testJoinsASoldOutToItsOwnMerchant(): void
    {
        $case = new SoldOutCase(
            $this->soldOut('M1'),
            new MerchantRecord('M1', SizeBand::B),
            'One',
            'Leeds',
            null,
            false,
        );

        self::assertSame('M1', $case->merchant->merchantId);
    }

    public function testRefusesAnotherMerchant(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Sold-out "SO-1" belongs to merchant "M1", not "M2".');

        new SoldOutCase($this->soldOut('M1'), new MerchantRecord('M2', SizeBand::B), 'Two', 'York', 'R1', false);
    }

    private function soldOut(string $merchantId): SoldOutRecord
    {
        return new SoldOutRecord('SO-1', 'D1', $merchantId, new \DateTimeImmutable('2025-09-01'), null, null);
    }
}
