<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes private Mercure updates. Topics are namespaced per user so a subscriber
 * token can only ever see its owner's events.
 */
final class RealtimePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MERCURE_TOPIC_BASE')]
        private readonly string $topicBase,
        #[Autowire(env: 'bool:MERCURE_ENABLED')]
        private readonly bool $enabled,
    ) {
    }

    public function userTopic(User|string $user): string
    {
        $id = $user instanceof User ? $user->getId()->toRfc4122() : $user;

        return rtrim($this->topicBase, '/').'/users/'.$id;
    }

    public function runTopic(User|string $user, string $runId): string
    {
        return $this->userTopic($user).'/runs/'.$runId;
    }

    /** @return list<string> topic selectors a user may subscribe to */
    public function subscriberTopics(User $user): array
    {
        return [$this->userTopic($user), $this->userTopic($user).'/runs/{runId}'];
    }

    /** @param array<string, mixed> $payload */
    public function publish(string $topic, string $event, array $payload): void
    {
        if (!$this->enabled) {
            return;
        }
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode(['event' => $event, 'data' => $payload], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                private: true,
                type: $event,
            ));
        } catch (\Throwable $e) {
            // Realtime is best-effort: the UI falls back to polling, never fail the business operation.
            $this->logger->warning('Mercure publish failed', ['topic' => $topic, 'error' => $e->getMessage()]);
        }
    }
}
