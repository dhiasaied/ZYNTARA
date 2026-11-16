<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** HTTP client for the Python LangGraph agents service. */
final class AgentServiceClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'AGENTS_SERVICE_URL')]
        private readonly string $baseUrl,
        #[Autowire(env: 'INTERNAL_API_TOKEN')]
        private readonly string $internalToken,
    ) {
    }

    /**
     * Executes a run synchronously (the worker waits). Intermediate steps are streamed back
     * through the /internal/runs/{id}/events callback.
     *
     * @param array<string, mixed> $spec
     *
     * @return array{output: string, usage: array{input_tokens: int, output_tokens: int}, tools_used: list<string>, cancelled?: bool}
     */
    public function execute(array $spec): array
    {
        return $this->post('/v1/runs/execute', $spec, 900);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{chunks: int}
     */
    public function ingest(array $payload): array
    {
        return $this->post('/v1/ingest', $payload, 600);
    }

    /** Asks the agents service to stop a run at the next graph step. */
    public function cancel(string $runId): void
    {
        try {
            $this->post('/v1/runs/'.$runId.'/cancel', [], 10);
        } catch (AgentServiceException $e) {
            $this->logger->warning('Could not propagate cancellation', ['run' => $runId, 'error' => $e->getMessage()]);
        }
    }

    public function deleteVectors(?string $documentId, ?string $knowledgeBaseId): void
    {
        $this->post('/v1/vectors/delete', array_filter([
            'document_id' => $documentId,
            'knowledge_base_id' => $knowledgeBaseId,
        ]), 60);
    }

    /**
     * @param list<string> $knowledgeBaseIds
     *
     * @return array{results: list<array<string, mixed>>}
     */
    public function search(array $knowledgeBaseIds, string $query, int $k = 5): array
    {
        return $this->post('/v1/search', ['knowledge_base_ids' => $knowledgeBaseIds, 'query' => $query, 'k' => $k], 60);
    }

    /** @return list<array<string, mixed>> provider list, cached for a minute */
    public function providers(): array
    {
        return $this->cache->get('agents.providers', function (ItemInterface $item): array {
            $item->expiresAfter(60);
            try {
                $response = $this->httpClient->request('GET', $this->baseUrl.'/v1/providers', [
                    'headers' => ['X-Internal-Token' => $this->internalToken],
                    'timeout' => 5,
                ]);

                return $response->toArray()['providers'] ?? [];
            } catch (ExceptionInterface $e) {
                $item->expiresAfter(5);
                $this->logger->warning('Agents service unreachable while listing providers', ['error' => $e->getMessage()]);

                return [['id' => 'mock', 'name' => 'ZYNTARA Mock (offline)', 'available' => true, 'models' => ['zyntara-mock-1'], 'default_model' => 'zyntara-mock-1']];
            }
        });
    }

    /** @return array{ok: bool, latency_ms: int, details?: array<string, mixed>, error?: string} */
    public function health(): array
    {
        $start = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/health', ['timeout' => 3]);
            $details = $response->toArray();

            return ['ok' => true, 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'details' => $details];
        } catch (ExceptionInterface $e) {
            return ['ok' => false, 'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, int $timeout): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
                'json' => $payload,
                'headers' => ['X-Internal-Token' => $this->internalToken],
                'timeout' => $timeout,
                'max_duration' => $timeout,
            ]);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $detail = $e->getMessage();
            if (method_exists($e, 'getResponse')) {
                try {
                    $body = $e->getResponse()->toArray(false);
                    $detail = \is_string($body['detail'] ?? null) ? $body['detail'] : $detail;
                } catch (\Throwable) {
                }
            }
            $this->logger->error('Agents service call failed', ['path' => $path, 'error' => $detail]);

            throw new AgentServiceException($detail, 0, $e);
        }
    }
}
