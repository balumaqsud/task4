<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class CurrentUserCheckerSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ROUTES = ['app_login', 'app_register', 'app_confirm_registration'];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Connection $connection,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['checkCurrentUser', -10]];
    }

    public function checkCurrentUser(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || in_array($event->getRequest()->attributes->get('_route'), self::PUBLIC_ROUTES, true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $authenticatedUser = $token?->getUser();
        if (!$authenticatedUser instanceof UserInterface || !$authenticatedUser instanceof User) {
            return;
        }

        // Note: session user data can be stale after another request blocks or deletes the row.
        $status = $this->connection->fetchOne(
            'SELECT status FROM users WHERE id = :id',
            ['id' => $authenticatedUser->getId()],
        );
        if ($status === false || $status === UserStatus::BLOCKED->value) {
            $this->tokenStorage->setToken(null);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
        }
    }
}
