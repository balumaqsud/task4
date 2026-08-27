<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationTest extends WebTestCase
{
    private object $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();
    }

    public function testActiveUserCanAccessProtectedRoute(): void
    {
        $this->loginUser($this->createUser(UserStatus::ACTIVE, 'active@example.com'));

        $this->client->request('GET', '/protected');

        self::assertResponseIsSuccessful();
    }

    public function testUnauthenticatedUserCannotAccessUserManagement(): void
    {
        $this->client->request('GET', '/users');

        self::assertResponseRedirects('/login');
    }

    public function testRootRedirectsAnonymousUsersToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testRootRedirectsAuthenticatedUsersToTheUserTable(): void
    {
        $this->loginUser($this->createUser(UserStatus::ACTIVE, 'home@example.com'));

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/users');
    }

    public function testAuthenticatedUserVisitingLoginIsRedirectedToTheUserTable(): void
    {
        $this->loginUser($this->createUser(UserStatus::ACTIVE, 'already-in@example.com'));

        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/users');
    }

    public function testInvalidPasswordShowsAFriendlyError(): void
    {
        $user = $this->createUser(UserStatus::ACTIVE, 'wrong-password@example.com');

        $this->submitLogin($user->getEmail(), 'not-the-password');

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
        self::assertSelectorTextNotContains('body', 'Invalid CSRF token.');
        self::assertSelectorTextNotContains('body', 'Invalid credentials.');
    }

    public function testDirectLoginRedirectsToUserManagementAndRecordsLastLogin(): void
    {
        $user = $this->createUser(UserStatus::ACTIVE, 'direct-login@example.com');

        $this->submitLogin($user->getEmail());

        self::assertResponseRedirects('/users');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table tbody');

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(User::class, $user->getId())?->getLastLoginAt());
    }

    public function testUnverifiedUserCanAccessProtectedRoute(): void
    {
        $this->loginUser($this->createUser(UserStatus::UNVERIFIED, 'unverified@example.com'));

        $this->client->request('GET', '/protected');

        self::assertResponseIsSuccessful();
    }

    public function testUnverifiedUserCanLogInWithOneCharacterPassword(): void
    {
        $user = $this->createUser(UserStatus::UNVERIFIED, 'one-character@example.com', 'x');

        $this->submitLogin($user->getEmail(), 'x');

        self::assertResponseRedirects('/users');
    }

    public function testLogoutEndsTheAuthenticatedSession(): void
    {
        $this->loginUser($this->createUser(UserStatus::ACTIVE, 'logout@example.com'));

        $crawler = $this->client->request('GET', '/users');
        $token = $crawler->filter('form[action="/logout"] input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/logout', ['_csrf_token' => $token]);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/protected');
        self::assertResponseRedirects('/login');
    }

    public function testLogoutRejectsAnInvalidCsrfToken(): void
    {
        $this->loginUser($this->createUser(UserStatus::ACTIVE, 'logout-csrf@example.com'));

        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalid-token']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testBlockedUserCannotLogIn(): void
    {
        $user = $this->createUser(UserStatus::BLOCKED, 'blocked@example.com');
        $this->submitLogin($user->getEmail());

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'This account is blocked.');
    }

    public function testBlockedAuthenticatedUserIsRedirectedOnNextProtectedRequest(): void
    {
        $user = $this->createUser(UserStatus::ACTIVE, 'becomes-blocked@example.com');
        $this->loginUser($user);
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE users SET status = :status WHERE id = :id',
            ['status' => UserStatus::BLOCKED->value, 'id' => $user->getId()],
        );
        $this->entityManager->clear();

        $this->client->request('GET', '/protected');

        self::assertResponseRedirects('/login');
    }

    public function testDeletedAuthenticatedUserIsRedirectedOnNextProtectedRequest(): void
    {
        $user = $this->createUser(UserStatus::ACTIVE, 'deleted@example.com');
        $this->loginUser($user);
        $managedUser = $this->entityManager->find(User::class, $user->getId());
        $this->entityManager->remove($managedUser);
        $this->entityManager->flush();

        $this->client->request('GET', '/protected');

        self::assertResponseRedirects('/login');
    }

    private function createUser(UserStatus $status, string $email, string $plainPassword = 'P@ssword123'): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail($email);
        $user->setStatus($status);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function loginUser(User $user): void
    {
        $this->client->request('GET', '/protected');
        self::assertResponseRedirects('/login');
        $this->submitLogin($user->getEmail());
        self::assertResponseRedirects('/protected');
    }

    private function submitLogin(string $email, string $password = 'P@ssword123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            '_username' => $email,
            '_password' => $password,
            '_csrf_token' => $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ]);
    }
}
