<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversNothing]
final class KernelBootTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());
    }
}
