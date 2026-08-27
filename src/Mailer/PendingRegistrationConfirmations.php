<?php

declare(strict_types=1);

namespace App\Mailer;

final class PendingRegistrationConfirmations
{
    /** @var list<int> */
    private array $userIds = [];

    public function add(int $userId): void
    {
        $this->userIds[] = $userId;
    }

    /**
     * @return list<int>
     */
    public function pull(): array
    {
        $userIds = $this->userIds;
        $this->userIds = [];

        return $userIds;
    }
}
