<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\Tests\Support\AgentsServiceMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

abstract class ApiTestBase extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = $this->em();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        AgentsServiceMock::reset();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email = 'ada@example.com', bool $admin = false): User
    {
        $user = (new User())->setEmail($email)->setFullName('Ada Lovelace')->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'Str0ng#Passw0rd'));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function token(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function api(string $method, string $url, ?User $as = null, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $headers = $options['headers'] ?? [];
        $headers['Accept'] ??= 'application/ld+json';
        if (null !== $as) {
            $headers['Authorization'] = 'Bearer '.$this->token($as);
        }
        if ('PATCH' === $method && isset($options['json'])) {
            $headers['Content-Type'] = 'application/merge-patch+json';
        }
        $options['headers'] = $headers;

        return $this->client->request($method, $url, $options);
    }

    /** @return list<object> messages waiting on an in-memory transport */
    protected function queued(string $transport): array
    {
        /** @var \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport $t */
        $t = static::getContainer()->get('messenger.transport.'.$transport);

        return array_map(static fn ($envelope) => $envelope->getMessage(), $t->getSent());
    }
}
