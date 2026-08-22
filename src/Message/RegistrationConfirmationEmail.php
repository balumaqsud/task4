<?php

declare(strict_types=1);

namespace App\Message;

final class RegistrationConfirmationEmail
{
    public function __construct(public readonly int $userId)
    {
    }
}
