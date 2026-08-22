<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationConfirmationTest extends WebTestCase
{
    private object $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();
    }

    public function testValidConfirmationActivatesUserAndInvalidatesToken(): void
    {
        $user = $this->createUser(UserStatus::UNVERIFIED, 'valid-token');
        $client = $this->client;

        $client->request('GET', '/register/confirm/valid-token');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $confirmedUser = $this->entityManager->find(User::class, $user->getId());
        self::assertSame(UserStatus::ACTIVE, $confirmedUser->getStatus());
        self::assertNull($confirmedUser->getVerificationToken());
    }

    public function testInvalidConfirmationTokenIsRejected(): void
    {
        $client = $this->client;

        $client->request('GET', '/register/confirm/not-found');

        self::assertResponseStatusCodeSame(400);
    }

    public function testBlockedUserRemainsBlockedAfterConfirmation(): void
    {
        $user = $this->createUser(UserStatus::BLOCKED, 'blocked-token');
        $client = $this->client;

        $client->request('GET', '/register/confirm/blocked-token');

        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $blockedUser = $this->entityManager->find(User::class, $user->getId());
        self::assertSame(UserStatus::BLOCKED, $blockedUser->getStatus());
    }

    private function createUser(UserStatus $status, string $token): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail($token . '@example.com');
        $user->setPassword('hashed-password');
        $user->setStatus($status);
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}