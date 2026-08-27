<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventSubscriber\DrainAsyncMessengerOnTerminateSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class DrainAsyncMessengerOnTerminateSubscriberTest extends TestCase
{
    public function testDrainsQueuedMessagesAfterTheResponseInProd(): void
    {
        $envelope = new Envelope(new \stdClass());
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls([$envelope], []);
        $transport->expects(self::once())->method('ack')->with($envelope);
        $transport->expects(self::never())->method('reject');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(static function (Envelope $dispatched) use ($envelope): bool {
            return $dispatched->getMessage() === $envelope->getMessage()
                && $dispatched->last(ReceivedStamp::class) instanceof ReceivedStamp;
        }))->willReturnCallback(static fn (Envelope $dispatched): Envelope => $dispatched);

        $subscriber = new DrainAsyncMessengerOnTerminateSubscriber(
            $transport,
            $bus,
            $this->createStub(LoggerInterface::class),
            'prod',
        );
        $subscriber->drain($this->terminateEvent());
    }

    public function testDoesNotDrainDuringTests(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::never())->method('get');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $subscriber = new DrainAsyncMessengerOnTerminateSubscriber(
            $transport,
            $bus,
            $this->createStub(LoggerInterface::class),
            'test',
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
