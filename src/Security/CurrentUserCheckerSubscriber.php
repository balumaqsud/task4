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
        if (!$event->isMainRequest() || $event->getRequest()->attributes->get('_route') === 'app_login') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $authenticatedUser = $token?->getUser();
        if (!$authenticatedUser instanceof UserInterface || !$authenticatedUser instanceof User) {
            return;
        }

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