<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\BasicAuthSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(BasicAuthSubscriber::class)]
final class BasicAuthSubscriberTest extends TestCase
{
    private const string USER = 'alice';
    private const string PASSWORD = 's3cret';

    public function testRequestWithoutCredentialsIsChallenged(): void
    {
        $event = $this->handle(Request::create('/'));

        self::assertChallenged($event);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wrongCredentials(): iterable
    {
        yield 'wrong user' => ['bob', self::PASSWORD];
        yield 'wrong password' => [self::USER, 'guess'];
        yield 'both wrong' => ['bob', 'guess'];
        yield 'empty user' => ['', self::PASSWORD];
        yield 'empty password' => [self::USER, ''];
        yield 'user prefix' => ['alic', self::PASSWORD];
        yield 'password with trailing space' => [self::USER, self::PASSWORD.' '];
    }

    #[DataProvider('wrongCredentials')]
    public function testWrongCredentialsAreChallenged(string $user, string $password): void
    {
        $event = $this->handle(self::requestWith($user, $password));

        self::assertChallenged($event);
    }

    public function testCorrectCredentialsLetTheRequestThrough(): void
    {
        $event = $this->handle(self::requestWith(self::USER, self::PASSWORD));

        self::assertNull($event->getResponse());
    }

    public function testCredentialsFromARawAuthorizationHeaderAreRead(): void
    {
        $request = Request::create('/', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::USER.':'.self::PASSWORD),
        ]);

        $event = $this->handle($request);

        self::assertNull($event->getResponse());
    }

    public function testMalformedAuthorizationHeaderIsChallenged(): void
    {
        $request = Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Basic not-base64!']);

        $event = $this->handle($request);

        self::assertChallenged($event);
    }

    public function testSubRequestIsIgnored(): void
    {
        $event = $this->handle(Request::create('/'), HttpKernelInterface::SUB_REQUEST);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingConfiguration(): iterable
    {
        yield 'no user' => ['', self::PASSWORD];
        yield 'no password' => [self::USER, ''];
        yield 'neither' => ['', ''];
    }

    #[DataProvider('missingConfiguration')]
    public function testMissingConfigurationFailsClosed(string $user, string $password): void
    {
        $subscriber = new BasicAuthSubscriber($user, $password);
        $event = self::event(self::requestWith($user, $password), HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(\RuntimeException::class);

        $subscriber->onKernelRequest($event);
    }

    private function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $event = self::event($request, $type);
        new BasicAuthSubscriber(self::USER, self::PASSWORD)->onKernelRequest($event);

        return $event;
    }

    private static function event(Request $request, int $type): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type);
    }

    private static function requestWith(string $user, string $password): Request
    {
        return Request::create('/', server: ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $password]);
    }

    private static function assertChallenged(RequestEvent $event): void
    {
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            'Basic realm="Sold-out rescue", charset="UTF-8"',
            $response->headers->get('WWW-Authenticate'),
        );
        self::assertSame('', $response->getContent());
    }
}
