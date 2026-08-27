<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventSubscriber\DrainAsyncMessengerOnTerminateSubscriber;
use App\Mailer\PendingRegistrationConfirmations;
use App\Message\RegistrationConfirmationEmail;
use App\MessageHandler\SendsRegistrationConfirmationEmail;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

final class DrainAsyncMessengerOnTerminateSubscriberTest extends TestCase
{
    public function testSendsPendingConfirmationAfterTheResponseInProd(): void
    {
        $pending = new PendingRegistrationConfirmations();
        $pending->add(42);
        $envelope = new Envelope(new RegistrationConfirmationEmail(42));

        $sender = $this->createMock(SendsRegistrationConfirmationEmail::class);
        $sender->expects(self::once())->method('__invoke')->with(self::callback(
            static fn (RegistrationConfirmationEmail $message): bool => $message->userId === 42,
        ));

        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->expects(self::once())->method('all')->with(50)->willReturn([$envelope]);
        $transport->expects(self::once())->method('ack')->with($envelope);

        $subscriber = new DrainAsyncMessengerOnTerminateSubscriber(
            $pending,
            $sender,
            $transport,
            $this->createStub(LoggerInterface::class),
            'prod',
        );
        $subscriber->drain($this->terminateEvent());
        self::assertSame([], $pending->pull());
    }

    public function testDoesNotSendDuringTests(): void
    {
        $pending = new PendingRegistrationConfirmations();
        $pending->add(42);
        $sender = $this->createMock(SendsRegistrationConfirmationEmail::class);
        $sender->expects(self::never())->method('__invoke');
        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->expects(self::never())->method('all');

        $subscriber = new DrainAsyncMessengerOnTerminateSubscriber(
            $pending,
            $sender,
            $transport,
            $this->createStub(LoggerInterface::class),
            'test',
        );
        $subscriber->drain($this->terminateEvent());
        self::assertSame([42], $pending->pull());
    }

    public function testLogsFailureWithoutAcknowledgingTheQueuedMessage(): void
    {
        $pending = new PendingRegistrationConfirmations();
        $pending->add(42);
        $sender = $this->createMock(SendsRegistrationConfirmationEmail::class);
        $sender->expects(self::once())->method('__invoke')->willThrowException(new \RuntimeException('smtp failed'));
        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->expects(self::never())->method('ack');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $subscriber = new DrainAsyncMessengerOnTerminateSubscriber(
            $pending,
            $sender,
            $transport,
            $logger,
            'prod',
        );
        $subscriber->drain($this->terminateEvent());
    }

    private function terminateEvent(): TerminateEvent
    {
        return new TerminateEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            new Response(),
        );
    }
}
