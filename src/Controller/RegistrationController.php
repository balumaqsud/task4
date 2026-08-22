<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Form\RegistrationFormType;
use App\Message\RegistrationConfirmationEmail;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MessageBusInterface $messageBus,
        ValidatorInterface $validator,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getEmail();
            $password = $user->getPassword();

            if ($password === '' || trim($password) === '') {
                $this->addFlash('error', 'Password is required.');

                return $this->render('registration/register.html.twig', ['form' => $form->createView()]);
            }

            $user->setName(trim((string) $user->getName()));
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setStatus(UserStatus::UNVERIFIED);
            $user->setVerificationToken(User::getUniqIdValue());
            $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

            $violations = $validator->validate($user);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }

                return $this->render('registration/register.html.twig', ['form' => $form->createView()]);
            }

            try {
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'This email address is already registered.');

                return $this->render('registration/register.html.twig', ['form' => $form->createView()]);
            }

            $messageBus->dispatch(new RegistrationConfirmationEmail($user->getId()));
            $this->addFlash('success', 'Registration successful. Please check your email to confirm your account.');

            return $this->redirectToRoute('app_register');
        }

        return $this->render('registration/register.html.twig', ['form' => $form->createView()]);
    }
}
