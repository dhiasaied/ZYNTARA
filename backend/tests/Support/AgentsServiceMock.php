<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stands in for the Python agents service in functional tests
 * (wired as framework.http_client.mock_response_factory in the test env).
 */
final class AgentsServiceMock
{
    /** @var list<array{method: string, url: string, body: mixed}> */
    public static array $calls = [];
    public static bool $failExecute = false;

    public static function reset(): void
    {
        self::$calls = [];
        self::$failExecute = false;
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $body = isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null;
        self::$calls[] = ['method' => $method, 'url' => $url, 'body' => $body];
        $path = (string) parse_url($url, \PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/v1/runs/execute') => self::$failExecute
                ? new MockResponse(json_encode(['detail' => 'Provider "openai" is not configured']), ['http_code' => 422])
                : new MockResponse(json_encode([
                    'output' => 'Mocked answer for: '.($body['input'] ?? ''),
                    'usage' => ['input_tokens' => 12, 'output_tokens' => 34],
                    'tools_used' => ['calculator'],
                ])),
            str_ends_with($path, '/v1/ingest') => new MockResponse(json_encode(['chunks' => 3])),
            str_ends_with($path, '/v1/search') => new MockResponse(json_encode(['results' => [['content' => 'Returns within 30 days', 'score' => 0.91, 'document_id' => 'x', 'filename' => 'policies.md']]])),
            str_ends_with($path, '/v1/providers') => new MockResponse(json_encode(['providers' => [['id' => 'mock', 'name' => 'Mock', 'available' => true, 'models' => ['zyntara-mock-1'], 'default_model' => 'zyntara-mock-1']]])),
            str_ends_with($path, '/health') => new MockResponse(json_encode(['status' => 'ok'])),
            default => new MockResponse(json_encode(['ok' => true])),
        };
    }
}
