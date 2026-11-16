<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class BasicAuthSubscriber implements EventSubscriberInterface
{
    private const string CHALLENGE = 'Basic realm="Sold-out rescue", charset="UTF-8"';

    public function __construct(
        private string $user,
        private string $password,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Above the router (32), so unknown paths are challenged too instead of answering 404 to strangers
        return [KernelEvents::REQUEST => ['onKernelRequest', 256]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->user === '' || $this->password === '') {
            throw new \RuntimeException('BASIC_AUTH_USER and BASIC_AUTH_PASSWORD must both be set.');
        }

        $request = $event->getRequest();
        $userMatches = hash_equals($this->user, $request->getUser() ?? '');
        $passwordMatches = hash_equals($this->password, $request->getPassword() ?? '');
        if ($userMatches && $passwordMatches) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => self::CHALLENGE]));
    }
}
