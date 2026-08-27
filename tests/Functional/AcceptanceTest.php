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

    public function testUserManagementRendersTheRequiredTableAndToolbar(): void
    {
        $currentUser = $this->createUser('Current', 'current@example.com', new \DateTimeImmutable('-5 minutes'));
        $this->createUserWithStatus('Unverified', 'unverified-ui@example.com', UserStatus::UNVERIFIED);
        $this->createUserWithStatus('Blocked', 'blocked-ui@example.com', UserStatus::BLOCKED);
        $this->client->loginUser($currentUser);

        $crawler = $this->client->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Name', 'Email', 'Status', 'Last seen'],
            $crawler->filter('thead th:not(:first-child)')->each(static fn ($node): string => trim($node->text())),
        );
        self::assertSame('THE APP', trim($crawler->filter('.users-brand')->text()));
        self::assertSame('post', strtolower((string) $crawler->filter('form[action="/logout"]')->attr('method')));
        self::assertCount(1, $crawler->filter('form[action="/logout"] input[name="_csrf_token"]'));
        self::assertSame(
            ['block', 'unblock', 'delete', 'delete_unverified'],
            $crawler->filter('#user-toolbar button[name="action"]')->each(static fn ($node): string => (string) $node->attr('value')),
        );
        self::assertSame('Block', trim($crawler->filter('button[value="block"]')->text()));
        self::assertCount(2, $crawler->filter('#user-toolbar button.btn-outline-primary'));
        self::assertCount(2, $crawler->filter('#user-toolbar button.btn-outline-danger'));
        self::assertCount(3, $crawler->filter('#user-toolbar button[aria-label][title][data-bs-toggle="tooltip"]'));
        self::assertCount(1, $crawler->filter('#user-filter[type="search"][placeholder="Filter"]'));
        self::assertCount(0, $crawler->filter('tbody button'));
        self::assertCount(1, $crawler->filter(sprintf('input.user-selection[value="%d"]:not([disabled])', $currentUser->getId())));
        self::assertCount(1, $crawler->filter('.blocked-user input.user-selection:not([disabled])'));
        self::assertSame(['Active', 'Unverified', 'Blocked'], array_values(array_unique(
            $crawler->filter('.user-status')->each(static fn ($node): string => trim($node->text())),
        )));
        self::assertCount(1, $crawler->filter('.last-seen[data-timestamp][title][data-bs-toggle="tooltip"]'));
        self::assertCount(2, $crawler->filter('.last-seen:not([data-timestamp])'));
        self::assertCount(1, $crawler->filter('#no-matching-users[hidden]'));
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
            return $email instanceof TemplatedEmail
                && $email->getTo()[0]->getAddress() === 'confirm@example.com'
                && $email->getFrom()[0]->getAddress() === 'no-reply@example.com'
                && $email->getSubject() === 'Confirm your account'
                && $email->getHtmlTemplate() === 'registration/confirmation_email.html.twig'
                && str_contains((string) $email->getContext()['confirmationUrl'], '/register/confirm/confirmation-token');
        }));
        $handler = new RegistrationConfirmationEmailHandler(
            $this->entityManager,
            $mailer,
            static::getContainer()->get('router.default'),
            static::getContainer()->get('logger'),
            'no-reply@example.com',
        );

        $handler(new RegistrationConfirmationEmail($user->getId()));
    }

    private function createUser(string $name, string $email, ?\DateTimeImmutable $lastLoginAt): User
    {
        return $this->createUserWithStatus($name, $email, UserStatus::ACTIVE, $lastLoginAt);
    }

    private function createUserWithStatus(string $name, string $email, UserStatus $status, ?\DateTimeImmutable $lastLoginAt = null): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPassword('hashed-password');
        $user->setStatus($status);
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
