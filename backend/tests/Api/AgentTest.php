<?php

namespace App\Tests\Api;

final class AgentTest extends ApiTestBase
{
    public function testClimbingTheLadder(): void
    {
        $user = $this->createUser();

        // APP: an LLM wrapped with a prompt
        $response = $this->api('POST', '/api/agents', $user, ['json' => [
            'name' => 'Email assistant',
            'systemPrompt' => 'You write emails.',
            'provider' => 'mock',
            'model' => 'zyntara-mock-1',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $agent = $response->toArray();
        self::assertSame('app', $agent['level']);
        $iri = $agent['@id'];

        // TOOLS
        $response = $this->api('PATCH', $iri, $user, ['json' => ['tools' => ['send_email', 'web_search']]]);
        self::assertResponseIsSuccessful();
        self::assertSame('tools', $response->toArray()['level']);

        // RAG
        $kb = $this->api('POST', '/api/knowledge_bases', $user, ['json' => ['name' => 'Policies']])->toArray();
        $response = $this->api('PATCH', $iri, $user, ['json' => ['knowledgeBases' => [$kb['@id']]]]);
        self::assertSame('rag', $response->toArray()['level']);
        self::assertSame('Policies', $response->toArray()['knowledgeBases'][0]['name']);

        // AGENT
        $response = $this->api('PATCH', $iri, $user, ['json' => ['autonomous' => true, 'maxIterations' => 8]]);
        self::assertSame('agent', $response->toArray()['level']);
    }

    public function testValidation(): void
    {
        $user = $this->createUser();
        $this->api('POST', '/api/agents', $user, ['json' => ['name' => '', 'systemPrompt' => '', 'tools' => ['rm_rf']]]);
        self::assertResponseStatusCodeSame(422);

        $this->api('POST', '/api/agents', $user, ['json' => ['name' => 'x', 'systemPrompt' => 'y', 'provider' => 'skynet']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTenantIsolation(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');

        $iri = $this->api('POST', '/api/agents', $alice, ['json' => ['name' => 'Private', 'systemPrompt' => 'secret']])->toArray()['@id'];
        $aliceKb = $this->api('POST', '/api/knowledge_bases', $alice, ['json' => ['name' => 'Alice docs']])->toArray()['@id'];

        $this->api('GET', $iri, $bob);
        self::assertResponseStatusCodeSame(404);
        $this->api('PATCH', $iri, $bob, ['json' => ['name' => 'Stolen']]);
        self::assertResponseStatusCodeSame(404);
        $this->api('DELETE', $iri, $bob);
        self::assertResponseStatusCodeSame(404);

        $list = $this->api('GET', '/api/agents', $bob)->toArray();
        self::assertSame(0, $list['totalItems']);

        // Bob cannot attach Alice's knowledge base to his own agent: the IRI is not even resolvable for him
        // (CurrentUserExtension), and OwnedByCurrentUser would reject it as a second line of defence.
        $this->api('POST', '/api/agents', $bob, ['json' => ['name' => 'Bob', 'systemPrompt' => 'p', 'knowledgeBases' => [$aliceKb]]]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->api('GET', '/api/agents', $bob)->toArray()['totalItems']);
    }

    public function testCrewNeedsTwoOwnAgents(): void
    {
        $user = $this->createUser();
        $a = $this->api('POST', '/api/agents', $user, ['json' => ['name' => 'Researcher', 'systemPrompt' => 'research']])->toArray()['@id'];
        $b = $this->api('POST', '/api/agents', $user, ['json' => ['name' => 'Reviewer', 'systemPrompt' => 'review']])->toArray()['@id'];

        $this->api('POST', '/api/crews', $user, ['json' => ['name' => 'Solo', 'goal' => 'g', 'members' => [['agent' => $a, 'role' => 'Researcher']]]]);
        self::assertResponseStatusCodeSame(422);

        $response = $this->api('POST', '/api/crews', $user, ['json' => [
            'name' => 'Brief crew',
            'goal' => 'Write a reviewed brief',
            'strategy' => 'sequential',
            'members' => [['agent' => $a, 'role' => 'Researcher', 'position' => 0], ['agent' => $b, 'role' => 'Reviewer', 'position' => 1]],
        ]]);
        self::assertResponseStatusCodeSame(201);
        $crew = $response->toArray();
        self::assertSame('agentic', $crew['level']);
        self::assertCount(2, $crew['members']);
        self::assertSame('Reviewer', $crew['members'][1]['agent']['name']);
    }
}
