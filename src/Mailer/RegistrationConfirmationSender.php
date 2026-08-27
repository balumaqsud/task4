<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Message\RegistrationConfirmationEmail;
use App\MessageHandler\SendsRegistrationConfirmationEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RegistrationConfirmationSender
{
    public function __construct(
        private readonly SendsRegistrationConfirmationEmail $sendConfirmationEmail,
        #[Autowire(service: 'monolog.logger.confirmation_mail')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(int $userId): void
    {
        try {
            ($this->sendConfirmationEmail)(new RegistrationConfirmationEmail($userId));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send registration confirmation email.', [
                'userId' => $userId,
                'exception' => $exception,
            ]);
        }
    }
}
