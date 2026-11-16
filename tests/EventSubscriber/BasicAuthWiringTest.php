<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversNothing]
final class BasicAuthWiringTest extends KernelTestCase
{
    public function testSoldOutListIsChallengedWithoutCredentials(): void
    {
        $response = self::bootKernel()->handle(Request::create('/'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertNotNull($response->headers->get('WWW-Authenticate'));
    }

    public function testUnknownPathIsChallengedWithoutCredentials(): void
    {
        $response = self::bootKernel()->handle(Request::create('/no-such-page'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testConfiguredCredentialsReachTheRouter(): void
    {
        $request = Request::create('/no-such-page', server: ['PHP_AUTH_USER' => 'test', 'PHP_AUTH_PW' => 'test']);

        $this->expectException(NotFoundHttpException::class);

        self::bootKernel()->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    }
}
