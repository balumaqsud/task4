<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserStatus;
use App\Message\RegistrationConfirmationEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class RegistrationTest extends WebTestCase
{
    private object $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\User')->execute();
    }

    public function testSuccessfulRegistration(): void
    {
        $client = $this->client;

        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));

        $client->submitForm('Register', [
            'registration_form[name]' => 'Alice Example',
            'registration_form[email]' => 'alice@example.com',
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/register');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        self::assertNotNull($user);
        self::assertSame('Alice Example', $user->getName());
        self::assertSame(UserStatus::UNVERIFIED, $user->getStatus());
        self::assertNotSame('', (string) $user->getVerificationToken());
    }

    public function testRegistrationDispatchesConfirmationMessage(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'registration_form[name]' => 'Alice Example',
            'registration_form[email]' => 'dispatch@example.com',
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/register');

        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(TransportInterface::class, $transport);
        $messages = iterator_to_array($transport->get());
        self::assertNotEmpty($messages);
        self::assertInstanceOf(RegistrationConfirmationEmail::class, $messages[0]->getMessage());

        foreach ($messages as $envelope) {
            $transport->ack($envelope);
        }
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'registration_form[name]' => 'Jane Example',
            'registration_form[email]' => 'jane@example.com',
            'registration_form[password]' => '',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('html', 'Password is required.');
    }

    public function testOneCharacterPasswordIsAcceptedAndHashed(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->client->submitForm('Register', [
            'registration_form[name]' => 'Short Password',
            'registration_form[email]' => 'short-password@example.com',
            'registration_form[password]' => 'x',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/register');
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'short-password@example.com']);
        self::assertNotNull($user);
        self::assertSame(UserStatus::UNVERIFIED, $user->getStatus());
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'x'));
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

        $client = $this->client;
        $crawler = $client->request('GET', '/register');

        $client->submitForm('Register', [
            'registration_form[name]' => 'New User',
            'registration_form[email]' => 'duplicate@example.com',
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('html', 'This email address is already registered.');
    }

    public function testRegistrationStoresAPasswordHash(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submitForm('Register', [
            'registration_form[name]' => 'Hashed User',
            'registration_form[email]' => 'hashed@example.com',
            'registration_form[password]' => 'P@ssword123',
            'registration_form[_token]' => $crawler->filter('input[name$="[_token]"]')->attr('value'),
        ]);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'hashed@example.com']);
        self::assertNotNull($user);
        self::assertNotSame('P@ssword123', $user->getPassword());
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'P@ssword123'));
    }
}
