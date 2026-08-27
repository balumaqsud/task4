<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserStatus;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->rejectBlockedUser($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->rejectBlockedUser($user);
    }

    private function rejectBlockedUser(UserInterface $user): void
    {
        // Note: blocked accounts must not authenticate, even with a valid password.
        if ($user instanceof User && $user->getStatus() === UserStatus::BLOCKED) {
            throw new CustomUserMessageAccountStatusException('This account is blocked.');
        }
    }
}