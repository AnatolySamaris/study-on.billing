<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiLoginControllerTest extends WebTestCase
{
    public function testAuthValidData(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'user@mail.ru',
            'password' => 'password',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(200);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('token', $response);
    }

    public function testAuthInvalidUsername(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'USER_NOtExists@mail.com',
            'password' => 'password',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'User with given username not found',
            $response['error']
        );
    }

    public function testAuthInvalidPassword(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'user@mail.ru',
            'password' => 'INVALID_PASSWORD',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(401);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'Invalid password',
            $response['error']
        );
    }

    public function testAuthEmptyCredentials(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => '',
            'password' => '11111111111',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'email: Email is mandatory;',
            $response['error']
        );
    }

    public function testAuthInvalidEmail(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'it is not even email',
            'password' => '1234531',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'email: Invalid email address;',
            $response['error']
        );
    }

    public function testAuthShortPassword(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'user@mail.ru',
            'password' => '123',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'password: Password must consist of at least 6 symbols;',
            $response['error']
        );
    }

    public function testRegisterValidData(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'new_user@mail.ru',
            'password' => 'password',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(201);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('token', $response);
    }

    public function testRegisterExistingUsername(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'user@mail.ru',
            'password' => 'password',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'User with this email already exists',
            $response['error']
        );
    }

    public function testRegisterEmptyCredentials(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => '',
            'password' => '111111111',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'email: Email is mandatory;',
            $response['error']
        );
    }

    public function testRegisterInvalidEmail(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'it is not even email',
            'password' => 'password',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'email: Invalid email address;',
            $response['error']
        );
    }

    public function testRegisterShortPassword(): void
    {
        $client = static::createClient();

        $credentials = [
            'email' => 'new_user@mail.ru',
            'password' => '123',
        ];

        $crawler = $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials)
        );

        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(
            'password: Password must consist of at least 6 symbols;',
            $response['error']
        );
    }
}
