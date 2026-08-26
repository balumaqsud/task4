<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BulkUserActionTest extends WebTestCase
{
    private object $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();

        $admin = $this->createUser(UserStatus::ACTIVE, 'admin@example.com');
        $this->client->loginUser($admin);
    }

    public function testBlocksMultipleUsers(): void
    {
        $users = [$this->createUser(UserStatus::ACTIVE, 'one@example.com'), $this->createUser(UserStatus::UNVERIFIED, 'two@example.com')];

        $this->submitAction('block', $users);

        self::assertResponseRedirects('/users');
        self::assertSame(2, $this->countWithStatus(UserStatus::BLOCKED, $users));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 user(s) blocked.');
    }

    public function testUnblocksMultipleUsers(): void
    {
        $users = [$this->createUser(UserStatus::BLOCKED, 'one@example.com'), $this->createUser(UserStatus::BLOCKED, 'two@example.com')];

        $this->submitAction('unblock', $users);

        self::assertResponseRedirects('/users');
        self::assertSame(2, $this->countWithStatus(UserStatus::ACTIVE, $users));
    }

    public function testDeletesMultipleUsers(): void
    {
        $users = [$this->createUser(UserStatus::ACTIVE, 'one@example.com'), $this->createUser(UserStatus::UNVERIFIED, 'two@example.com')];

        $this->submitAction('delete', $users);

        self::assertResponseRedirects('/users');
        self::assertSame(0, $this->countUsers($users));
    }

    public function testDeletesOnlyUnverifiedUsers(): void
    {
        $unverified = $this->createUser(UserStatus::UNVERIFIED, 'unverified@example.com');
        $active = $this->createUser(UserStatus::ACTIVE, 'active@example.com');

        $this->submitAction('delete_unverified', [$unverified, $active]);

        self::assertResponseRedirects('/users');
        self::assertNull($this->entityManager->find(User::class, $unverified->getId()));
        self::assertNotNull($this->entityManager->find(User::class, $active->getId()));
    }

    public function testRejectsInvalidAction(): void
    {
        $user = $this->createUser(UserStatus::ACTIVE, 'one@example.com');

        $this->submitAction('promote', [$user]);

        self::assertResponseRedirects('/users');
        self::assertSame(UserStatus::ACTIVE, $this->entityManager->find(User::class, $user->getId())->getStatus());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid bulk action or selection.');
    }

    public function testRejectsInvalidIds(): void
    {
        $this->submitAction('block', ['not-an-id']);

        self::assertResponseRedirects('/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid bulk action or selection.');
    }

    public function testRejectsNonexistentIds(): void
    {
        $this->submitAction('block', ['999999999']);

        self::assertResponseRedirects('/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'One or more selected users do not exist.');
    }

    public function testDatabaseFailureShowsSafeFeedback(): void
    {
        $this->submitAction('block', [(string) PHP_INT_MAX]);

        self::assertResponseRedirects('/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'The operation failed. Please try again.');
        self::assertSelectorTextNotContains('body', 'SQLSTATE');
    }

    public function testRejectsInvalidCsrfToken(): void
    {
        $this->client->request('POST', '/users/actions', [
            'action' => 'block',
            'ids' => ['1'],
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCurrentUserCanBeBlockedWithAnotherUserAndIsRejectedNextRequest(): void
    {
        $currentUser = $this->findUserByEmail('admin@example.com');
        $otherUser = $this->createUser(UserStatus::ACTIVE, 'other@example.com');

        $this->submitAction('block', [$currentUser, $otherUser]);

        self::assertResponseRedirects('/users');
        $this->entityManager->clear();
        self::assertSame(UserStatus::BLOCKED, $this->entityManager->find(User::class, $currentUser->getId())->getStatus());
        self::assertSame(UserStatus::BLOCKED, $this->entityManager->find(User::class, $otherUser->getId())->getStatus());

        $this->client->request('GET', '/protected');

        self::assertResponseRedirects('/login');
    }

    public function testCurrentUserCanBeDeletedWithAnotherUserAndEmailCanRegisterAgain(): void
    {
        $currentUser = $this->findUserByEmail('admin@example.com');
        $currentEmail = $currentUser->getEmail();
        $otherUser = $this->createUser(UserStatus::ACTIVE, 'other@example.com');

        $this->submitAction('delete', [$currentUser, $otherUser]);

        self::assertResponseRedirects('/users');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(User::class, $currentUser->getId()));
        self::assertNull($this->entityManager->find(User::class, $otherUser->getId()));

        $this->client->request('GET', '/protected');
        self::assertResponseRedirects('/login');

        $crawler = $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[name]' => 'Registered Again',
            'registration_form[email]' => $currentEmail,
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/register');
    }

    private function submitAction(string $action, array $users): void
    {
        $crawler = $this->client->request('GET', '/users');
        $ids = array_map(static fn (User|string $user): int|string => $user instanceof User ? $user->getId() : $user, $users);
        $this->client->request('POST', '/users/actions', [
            'action' => $action,
            'ids' => $ids,
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
        ]);
    }

    private function createUser(UserStatus $status, string $email): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail($email);
        $user->setPassword('hashed-password');
        $user->setStatus($status);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function findUserByEmail(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertNotNull($user);

        return $user;
    }

    private function countWithStatus(UserStatus $status, array $users): int
    {
        $count = 0;
        foreach ($users as $user) {
            $this->entityManager->clear();
            if ($this->entityManager->find(User::class, $user->getId())?->getStatus() === $status) {
                ++$count;
            }
        }

        return $count;
    }

    private function countUsers(array $users): int
    {
        $count = 0;
        foreach ($users as $user) {
            if ($this->entityManager->find(User::class, $user->getId()) !== null) {
                ++$count;
            }
        }

        return $count;
    }
}
