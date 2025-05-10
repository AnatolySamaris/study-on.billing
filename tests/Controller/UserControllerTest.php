<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends WebTestCase
{
    public function testGetCurrentUserValidData(): void
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

        # Проверка залогиненного юзера
        $crawler = $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $response['token']]
        );

        $this->assertResponseStatusCodeSame(200);

        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('roles', $response);
        $this->assertArrayHasKey('balance', $response);

        # Проверка, что получили нужного юзера
        $this->assertEquals('user@mail.ru', $response['username']);
        $this->assertEquals(1, count($response['roles']));
        $this->assertEquals('ROLE_USER', $response['roles'][0]);
        
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $currentUser = $entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => 'user@mail.ru'])
        ;
        $this->assertEquals($currentUser->getBalance(), $response['balance']);
    }

    public function testGetCurrentUserInvalidToken(): void
    {
        $client = static::createClient();
        
        $token = '123';
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(401);

        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );
        
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Invalid JWT Token', $response['message']);
    }

    public function testGetCurrentUserMissingToken(): void
    {
        $client = static::createClient();

        $token = '';
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(401);

        $response = json_decode(
            $client->getResponse()->getContent(),
            true
        );
        
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('JWT Token not found', $response['message']);
    }
}
