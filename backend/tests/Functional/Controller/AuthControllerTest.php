<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Api\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testRegisterSuccess(): void
    {
        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertEquals('Registrace úspěšná', $data['message']);
        $this->assertEquals('New User', $data['user']['name']);
        $this->assertEquals('newuser@example.com', $data['user']['email']);
        $this->assertArrayHasKey('id', $data['user']);
    }

    public function testRegisterMissingFields(): void
    {
        $this->apiRequest('POST', '/api/auth/register', [
            'email' => 'test@example.com',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    public function testRegisterPasswordTooShort(): void
    {
        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->getResponseData();
        $this->assertStringContainsString('6', $data['error']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $this->createUser('existing@example.com');

        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMeEndpointRequiresAuth(): void
    {
        $this->apiRequest('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeEndpointReturnsUserData(): void
    {
        $user = $this->createUser('auth@example.com', 'password123', 'Auth User');
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertEquals('Auth User', $data['name']);
        $this->assertEquals('auth@example.com', $data['email']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testLoginSuccess(): void
    {
        $this->createUser('login@example.com', 'mypassword', 'Login User');

        $this->apiRequest('POST', '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'mypassword',
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->createUser('user@example.com', 'correctpassword');

        $this->apiRequest('POST', '/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginNonExistentUser(): void
    {
        $this->apiRequest('POST', '/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
