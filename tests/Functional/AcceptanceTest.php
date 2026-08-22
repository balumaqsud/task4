<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Message\RegistrationConfirmationEmail;
use App\MessageHandler\RegistrationConfirmationEmailHandler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;

final class AcceptanceTest extends WebTestCase
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

    public function testUsersAreSortedByLastLoginDescending(): void
    {
        $this->createUser('Older', 'older@example.com', new \DateTimeImmutable('-2 hours'));
        $this->createUser('Newer', 'newer@example.com', new \DateTimeImmutable('-1 hour'));
        $client = $this->client;
        $admin = $this->createUser('Admin', 'admin@example.com', null);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/users');
        $names = $crawler->filter('tbody tr td:nth-child(2)')->each(static fn ($node): string => trim($node->text()));

        self::assertSame(['Newer', 'Older', 'Admin'], $names);
    }

    public function testPostgresEnforcesTheEmailUniqueIndex(): void
    {
        $connection = $this->entityManager->getConnection();
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_email_unique_idx' AND indexdef LIKE '%UNIQUE%'"));

        $connection->insert('users', $this->userRow('unique@example.com'));
        $this->expectException(UniqueConstraintViolationException::class);
        $connection->insert('users', $this->userRow('unique@example.com'));
    }

    public function testDuplicateInsertConstraintIsTranslatedByRegistration(): void
    {
        $user = new User();
        $user->setName('Existing User');
        $user->setEmail('constraint@example.com');
        $user->setPassword('hashed-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $client = $this->client;
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'registration_form[name]' => 'Duplicate User',
            'registration_form[email]' => 'constraint@example.com',
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'This email address is already registered.');
    }

    public function testConfirmationHandlerSendsTheConfirmationEmail(): void
    {
        $user = $this->createUser('Confirm', 'confirm@example.com', null);
        $user->setVerificationToken('confirmation-token');
        $this->entityManager->flush();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function ($email): bool {
            return $email instanceof TemplatedEmail && $email->getTo()[0]->getAddress() === 'confirm@example.com';
        }));
        $handler = new RegistrationConfirmationEmailHandler(
            $this->entityManager,
            $mailer,
            static::getContainer()->get('router.default'),
        );

        $handler(new RegistrationConfirmationEmail($user->getId()));
    }

    private function createUser(string $name, string $email, ?\DateTimeImmutable $lastLoginAt): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPassword('hashed-password');
        $user->setStatus(UserStatus::ACTIVE);
        $user->setLastLoginAt($lastLoginAt);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function userRow(string $email): array
    {
        return [
            'name' => 'Database User',
            'email' => $email,
            'password' => 'hashed-password',
            'status' => UserStatus::ACTIVE->value,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}