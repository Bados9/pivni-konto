<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Api\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testRegisterSuccess(): void
    {
        $email = 'newuser_' . uniqid() . '@example.com';

        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'New User',
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getResponseData();
        $this->assertEquals('Registrace úspěšná', $data['message']);
        $this->assertEquals('New User', $data['user']['name']);
        $this->assertEquals($email, $data['user']['email']);
        $this->assertArrayHasKey('id', $data['user']);
    }

    public function testRegisterMissingFields(): void
    {
        $this->apiRequest('POST', '/api/auth/register', [
            'email' => 'test_' . uniqid() . '@example.com',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    public function testRegisterPasswordTooShort(): void
    {
        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => '123',
        ]);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->getResponseData();
        $this->assertStringContainsString('6', $data['error']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $email = 'existing_' . uniqid() . '@example.com';
        $this->createUser($email);

        $this->apiRequest('POST', '/api/auth/register', [
            'name' => 'Another User',
            'email' => $email,
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
        $user = $this->createUser(null, 'password123', 'Auth User');
        $this->loginAs($user);

        $this->apiRequest('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertEquals('Auth User', $data['name']);
        $this->assertEquals($user->getEmail(), $data['email']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testLoginSuccess(): void
    {
        $email = 'login_' . uniqid() . '@example.com';
        $this->createUser($email, 'mypassword', 'Login User');

        $this->apiRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'mypassword',
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = $this->getResponseData();
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginInvalidCredentials(): void
    {
        $email = 'user_' . uniqid() . '@example.com';
        $this->createUser($email, 'correctpassword');

        $this->apiRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginNonExistentUser(): void
    {
        $this->apiRequest('POST', '/api/auth/login', [
            'email' => 'nonexistent_' . uniqid() . '@example.com',
            'password' => 'anypassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
