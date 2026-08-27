<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Mailer\PendingRegistrationConfirmations;
use App\Message\RegistrationConfirmationEmail;
use App\MessageHandler\SendsRegistrationConfirmationEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * Confirmation mail is queued during registration, then sent after the HTTP response.
 */
final class DrainAsyncMessengerOnTerminateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PendingRegistrationConfirmations $pendingConfirmations,
        private readonly SendsRegistrationConfirmationEmail $sendConfirmationEmail,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly ListableReceiverInterface $asyncTransport,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['drain', -1024]];
    }

    public function drain(TerminateEvent $event): void
    {
        if (!$event->isMainRequest() || $this->environment === 'test') {
            return;
        }

        foreach ($this->pendingConfirmations->pull() as $userId) {
            try {
                ($this->sendConfirmationEmail)(new RegistrationConfirmationEmail($userId));
                $this->ackQueuedMessage($userId);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to send registration confirmation email.', [
                    'userId' => $userId,
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function ackQueuedMessage(int $userId): void
    {
        foreach ($this->asyncTransport->all(50) as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof RegistrationConfirmationEmail && $message->userId === $userId) {
                $this->asyncTransport->ack($envelope);
            }
        }
    }
}
