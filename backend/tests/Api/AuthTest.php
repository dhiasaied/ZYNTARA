<?php

namespace App\Tests\Api;

final class AuthTest extends ApiTestBase
{
    public function testRegisterLoginAndProfile(): void
    {
        $response = $this->api('POST', '/api/auth/register', null, ['json' => [
            'email' => 'Grace@Example.com', 'fullName' => 'Grace Hopper', 'password' => 'C0mpiler#Nanos',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        self::assertNotEmpty($data['token']);
        self::assertSame('grace@example.com', $data['user']['email']);
        self::assertTrue($data['user']['isAdmin'], 'The first account of an installation is the administrator.');

        $login = $this->api('POST', '/api/auth/login', null, ['json' => ['email' => 'grace@example.com', 'password' => 'C0mpiler#Nanos']]);
        self::assertResponseIsSuccessful();
        $token = $login->toArray()['token'];
        self::assertSame('Grace Hopper', $login->toArray()['user']['fullName']);

        $me = $this->client->request('GET', '/api/me', ['headers' => ['Authorization' => 'Bearer '.$token]]);
        self::assertResponseIsSuccessful();
        self::assertSame('grace@example.com', $me->toArray()['email']);
    }

    public function testSecondUserIsNotAdminAndDuplicatesAreRejected(): void
    {
        $this->createUser('first@example.com');
        $response = $this->api('POST', '/api/auth/register', null, ['json' => ['email' => 'b@example.com', 'fullName' => 'Bob', 'password' => 'An0ther#Secret']]);
        self::assertResponseStatusCodeSame(201);
        self::assertFalse($response->toArray()['user']['isAdmin']);

        $this->api('POST', '/api/auth/register', null, ['json' => ['email' => 'b@example.com', 'fullName' => 'Bob', 'password' => 'An0ther#Secret']]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testInvalidRegistrationIsRejected(): void
    {
        $this->api('POST', '/api/auth/register', null, ['json' => ['email' => 'not-an-email', 'fullName' => 'X', 'password' => 'short']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testWrongPasswordAndDeactivatedAccount(): void
    {
        $user = $this->createUser();
        $this->api('POST', '/api/auth/login', null, ['json' => ['email' => 'ada@example.com', 'password' => 'nope']]);
        self::assertResponseStatusCodeSame(401);

        $user->setActive(false);
        $this->em()->flush();
        $this->api('POST', '/api/auth/login', null, ['json' => ['email' => 'ada@example.com', 'password' => 'Str0ng#Passw0rd']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedRoutesNeedAToken(): void
    {
        $this->api('GET', '/api/agents');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdateProfileAndPassword(): void
    {
        $user = $this->createUser();
        $response = $this->api('PATCH', '/api/me', $user, ['json' => ['fullName' => 'Ada King', 'preferences' => ['defaultProvider' => 'anthropic', 'defaultModel' => 'claude-sonnet-5-5']]]);
        self::assertResponseIsSuccessful();
        self::assertSame('Ada King', $response->toArray()['fullName']);
        self::assertSame('anthropic', $response->toArray()['preferences']['defaultProvider']);

        $this->api('POST', '/api/me/password', $user, ['json' => ['currentPassword' => 'wrong', 'newPassword' => 'N3w#Password!']]);
        self::assertResponseStatusCodeSame(422);
        $this->api('POST', '/api/me/password', $user, ['json' => ['currentPassword' => 'Str0ng#Passw0rd', 'newPassword' => 'N3w#Password!']]);
        self::assertResponseIsSuccessful();
    }

    public function testAdminAreaIsRestricted(): void
    {
        $user = $this->createUser();
        $this->api('GET', '/api/admin/users', $user);
        self::assertResponseStatusCodeSame(403);

        $admin = $this->createUser('root@example.com', true);
        $response = $this->api('GET', '/api/admin/users', $admin);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $response->toArray()['total']);

        $this->api('PATCH', '/api/admin/users/'.$user->getId()->toRfc4122(), $admin, ['json' => ['active' => false], 'headers' => ['Content-Type' => 'application/json']]);
        self::assertResponseIsSuccessful();
        $this->api('PATCH', '/api/admin/users/'.$admin->getId()->toRfc4122(), $admin, ['json' => ['roles' => ['ROLE_USER']], 'headers' => ['Content-Type' => 'application/json']]);
        self::assertResponseStatusCodeSame(422);
    }
}
