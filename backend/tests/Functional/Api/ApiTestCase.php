<?php

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    protected ?string $authToken = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Begin transaction for test isolation
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    protected function createUser(string $email = 'test@example.com', string $password = 'password123', string $name = 'Test User'): User
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function loginAs(User $user): void
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $this->authToken = $jwtManager->create($user);
    }

    protected function apiRequest(string $method, string $uri, array $data = [], array $headers = []): void
    {
        $defaultHeaders = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($this->authToken !== null) {
            $defaultHeaders['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $allHeaders,
            $data !== [] ? json_encode($data) : null
        );
    }

    protected function getResponseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        return json_decode($content, true) ?? [];
    }
}
