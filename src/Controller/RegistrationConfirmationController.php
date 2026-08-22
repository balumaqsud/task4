<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class RegistrationConfirmationController extends AbstractController
{
    #[Route('/register/confirm/{token}', name: 'app_confirm_registration', methods: ['GET'])]
    public function confirm(
        string $token,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);
        if ($user === null || $user->getVerificationTokenExpiresAt() === null || $user->getVerificationTokenExpiresAt() <= new \DateTimeImmutable()) {
            return new Response('Invalid or expired confirmation link.', Response::HTTP_BAD_REQUEST);
        }

        if ($user->getStatus() === UserStatus::UNVERIFIED) {
            $user->setStatus(UserStatus::ACTIVE);
        }

        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $entityManager->flush();

        return new Response('Your account has been confirmed.');
    }
}