<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\RegistrationConfirmationEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class RegistrationConfirmationEmailHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RegistrationConfirmationEmail $message): void
    {
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);
        if ($user === null || $user->getVerificationToken() === null) {
            return;
        }

        $confirmationUrl = $this->urlGenerator->generate('app_confirm_registration', [
            'token' => $user->getVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Confirm your account')
            ->htmlTemplate('registration/confirmation_email.html.twig')
            ->context(['confirmationUrl' => $confirmationUrl]);

        $this->mailer->send($email);
    }
}