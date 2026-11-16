<?php

namespace App\Tests\Api;

use App\Entity\Run;
use App\Message\ExecuteRun;
use App\MessageHandler\ExecuteRunHandler;
use App\Tests\Support\AgentsServiceMock;

final class RunTest extends ApiTestBase
{
    public function testPromptRunLifecycle(): void
    {
        $user = $this->createUser();
        $response = $this->api('POST', '/api/runs', $user, ['json' => [
            'kind' => 'prompt', 'input' => 'What is an LLM?', 'provider' => 'mock', 'model' => 'zyntara-mock-1',
        ]]);
        self::assertResponseStatusCodeSame(201);
        $run = $response->toArray();
        self::assertSame('queued', $run['status']);

        $queued = $this->queued('runs');
        self::assertCount(1, $queued);
        self::assertInstanceOf(ExecuteRun::class, $queued[0]);

        // Agents service streams a step back through the internal callback
        $this->client->request('POST', '/internal/runs/'.$run['id'].'/events', [
            'headers' => ['X-Internal-Token' => 'test-internal-token'],
            'json' => ['events' => [['type' => 'thought', 'actor' => 'LLM', 'content' => 'Thinking…'], ['type' => 'bogus']]],
        ]);
        self::assertResponseIsSuccessful();

        // Worker executes the run
        static::getContainer()->get(ExecuteRunHandler::class)(new ExecuteRun($run['id']));

        $detail = $this->api('GET', '/api/runs/'.$run['id'], $user)->toArray();
        self::assertSame('succeeded', $detail['status']);
        self::assertSame('Mocked answer for: What is an LLM?', $detail['output']);
        self::assertSame(46, $detail['tokensIn'] + $detail['tokensOut']);
        self::assertCount(1, $detail['steps']);
        self::assertSame('thought', $detail['steps'][0]['type']);

        $spec = AgentsServiceMock::$calls[0]['body'];
        self::assertSame('prompt', $spec['kind']);
        self::assertSame('mock', $spec['prompt']['provider']);
        self::assertStringEndsWith('/internal/runs/'.$run['id'].'/events', $spec['callback']['url']);

        $notifications = $this->api('GET', '/api/notifications', $user)->toArray();
        self::assertSame(1, $notifications['totalItems']);
        self::assertSame('run_succeeded', $notifications['member'][0]['type']);
    }

    public function testInternalCallbackRequiresTheSecret(): void
    {
        $user = $this->createUser();
        $run = $this->api('POST', '/api/runs', $user, ['json' => ['kind' => 'prompt', 'input' => 'hi', 'provider' => 'mock', 'model' => 'm']])->toArray();
        $this->client->request('POST', '/internal/runs/'.$run['id'].'/events', ['headers' => ['X-Internal-Token' => 'guess'], 'json' => ['type' => 'final']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testFailedRunIsReportedAndCanBeRetried(): void
    {
        $user = $this->createUser();
        $run = $this->api('POST', '/api/runs', $user, ['json' => ['kind' => 'prompt', 'input' => 'hi', 'provider' => 'openai', 'model' => 'gpt-5']])->toArray();
        AgentsServiceMock::$failExecute = true;
        static::getContainer()->get(ExecuteRunHandler::class)(new ExecuteRun($run['id']));

        $detail = $this->api('GET', '/api/runs/'.$run['id'], $user)->toArray();
        self::assertSame('failed', $detail['status']);
        self::assertStringContainsString('not configured', $detail['error']);

        $retry = $this->api('POST', '/api/runs/'.$run['id'].'/retry', $user, ['json' => []]);
        self::assertResponseStatusCodeSame(202);
        self::assertNotSame($run['id'], $retry->toArray()['id']);
    }

    public function testCancelAndValidation(): void
    {
        $user = $this->createUser();
        $this->api('POST', '/api/runs', $user, ['json' => ['kind' => 'agent', 'input' => 'hi']]);
        self::assertResponseStatusCodeSame(422);

        $run = $this->api('POST', '/api/runs', $user, ['json' => ['kind' => 'prompt', 'input' => 'hi', 'provider' => 'mock', 'model' => 'm']])->toArray();
        $response = $this->api('POST', '/api/runs/'.$run['id'].'/cancel', $user, ['json' => []]);
        self::assertSame('cancelled', $response->toArray()['status']);

        // The worker skips runs that are no longer queued
        static::getContainer()->get(ExecuteRunHandler::class)(new ExecuteRun($run['id']));
        $this->em()->clear();
        self::assertSame('cancelled', $this->em()->find(Run::class, $run['id'])->getStatus()->value);

        $other = $this->createUser('eve@example.com');
        $this->api('POST', '/api/runs/'.$run['id'].'/cancel', $other, ['json' => []]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testChatConversation(): void
    {
        $user = $this->createUser();
        $agent = $this->api('POST', '/api/agents', $user, ['json' => ['name' => 'Tutor', 'systemPrompt' => 'teach', 'tools' => ['calculator']]])->toArray();
        $conversation = $this->api('POST', '/api/conversations', $user, ['json' => ['agent' => $agent['@id']]])->toArray();
        self::assertResponseStatusCodeSame(201);

        $posted = $this->api('POST', '/api/conversations/'.$conversation['id'].'/messages', $user, ['json' => ['content' => 'Explain RAG in one line']]);
        self::assertResponseStatusCodeSame(202);
        $runId = $posted->toArray()['run']['id'];
        self::assertSame('Explain RAG in one line', $posted->toArray()['title']);

        static::getContainer()->get(ExecuteRunHandler::class)(new ExecuteRun($runId));

        $thread = $this->api('GET', '/api/conversations/'.$conversation['id'], $user)->toArray();
        self::assertCount(2, $thread['messages']);
        self::assertSame('assistant', $thread['messages'][1]['role']);
        self::assertSame(['calculator'], AgentsServiceMock::$calls[0]['body']['agent']['tools']);

        // Chat runs do not spam notifications
        self::assertSame(0, $this->api('GET', '/api/notifications', $user)->toArray()['totalItems']);
    }

    public function testDashboardAndAnalytics(): void
    {
        $user = $this->createUser();
        $this->api('POST', '/api/agents', $user, ['json' => ['name' => 'A', 'systemPrompt' => 'p', 'autonomous' => true]]);
        $dashboard = $this->api('GET', '/api/dashboard', $user)->toArray();
        self::assertSame(1, $dashboard['counts']['agents']);
        $ladder = array_column($dashboard['ladder'], 'count', 'level');
        self::assertSame(1, $ladder['agent']);

        $analytics = $this->api('GET', '/api/analytics?days=7', $user)->toArray();
        self::assertCount(7, $analytics['series']);
    }
}
