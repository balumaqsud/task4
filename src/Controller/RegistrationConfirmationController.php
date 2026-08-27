<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserStatus;
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
            $this->addFlash('error', 'Invalid or expired confirmation link.');

            return $this->redirectToRoute('app_login');
        }

        if ($user->getStatus() === UserStatus::UNVERIFIED) {
            $user->setStatus(UserStatus::ACTIVE);
        }

        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Your account has been confirmed.');

        return $this->redirectToRoute('app_login');
    }
}
