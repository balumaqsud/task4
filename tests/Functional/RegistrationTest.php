<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();
    }

    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));

        $client->submitForm('Register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'P@ssword123',
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/register');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        self::assertNotNull($user);
        self::assertSame('Alice Example', $user->getName());
        self::assertSame(UserStatus::UNVERIFIED, $user->getStatus());
        self::assertNotSame('', (string) $user->getVerificationToken());
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'name' => 'Jane Example',
            'email' => 'jane@example.com',
            'password' => '',
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('html', 'Password is required.');
    }

    public function testDuplicateEmailIsHandledByDatabaseConstraint(): void
    {
        $user = new User();
        $user->setName('Existing User');
        $user->setEmail('duplicate@example.com');
        $user->setPassword('hashed-password');
        $user->setVerificationToken(User::getUniqIdValue());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'name' => 'New User',
            'email' => 'duplicate@example.com',
            'password' => 'P@ssword123',
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('html', 'This email address is already registered.');
    }
}
