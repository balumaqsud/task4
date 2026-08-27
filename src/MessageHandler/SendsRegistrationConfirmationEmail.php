<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RegistrationConfirmationEmail;

interface SendsRegistrationConfirmationEmail
{
    public function __invoke(RegistrationConfirmationEmail $message): void;
}
