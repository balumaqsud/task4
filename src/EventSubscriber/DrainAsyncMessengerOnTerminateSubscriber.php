<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Render's free web process cannot keep a reliable background worker.
 * Messages stay async (queued during the request) and are sent after the response.
 */
final class DrainAsyncMessengerOnTerminateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private readonly TransportInterface $asyncTransport,
        private readonly MessageBusInterface $messageBus,
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

        for ($i = 0; $i < 10; ++$i) {
            $envelopes = $this->asyncTransport->get();
            if ($envelopes === []) {
                return;
            }

            foreach ($envelopes as $envelope) {
                try {
                    $this->messageBus->dispatch($envelope->with(new ReceivedStamp('async')));
                    $this->asyncTransport->ack($envelope);
                } catch (\Throwable $exception) {
                    $this->asyncTransport->reject($envelope);
                    $this->logger->error('Failed to process queued confirmation email.', [
                        'exception' => $exception,
                    ]);
                }
            }
        }
    }
}
