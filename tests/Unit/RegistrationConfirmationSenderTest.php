<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mailer\RegistrationConfirmationSender;
use App\Message\RegistrationConfirmationEmail;
use App\MessageHandler\SendsRegistrationConfirmationEmail;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RegistrationConfirmationSenderTest extends TestCase
{
    public function testSendInvokesTheConfirmationHandler(): void
    {
        $handler = $this->createMock(SendsRegistrationConfirmationEmail::class);
        $handler->expects(self::once())->method('__invoke')->with(self::callback(
            static fn (RegistrationConfirmationEmail $message): bool => $message->userId === 7,
        ));

        $sender = new RegistrationConfirmationSender($handler, $this->createStub(LoggerInterface::class));
        $sender->send(7);
    }

    public function testSendLogsFailuresWithoutThrowing(): void
    {
        $handler = $this->createMock(SendsRegistrationConfirmationEmail::class);
        $handler->expects(self::once())->method('__invoke')->willThrowException(new \RuntimeException('smtp failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $sender = new RegistrationConfirmationSender($handler, $logger);
        $sender->send(7);
    }
}
