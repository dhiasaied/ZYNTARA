<?php

namespace App\Service;

use App\Entity\Run;
use App\Entity\RunStep;
use App\Enum\StepType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Receives step events streamed by the agents service, persists them as RunSteps and
 * forwards them live to the browser. "token" events are forwarded only (not stored).
 */
final class RunEventRecorder
{
    private const MAX_CONTENT = 60000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RealtimePublisher $realtime,
    ) {
    }

    /**
     * @param array{type?: mixed, actor?: mixed, content?: mixed, metadata?: mixed} $event
     *
     * @throws \InvalidArgumentException when the payload is malformed
     */
    public function record(Run $run, array $event): ?RunStep
    {
        $type = \is_string($event['type'] ?? null) ? StepType::tryFrom($event['type']) : null;
        if (null === $type) {
            throw new \InvalidArgumentException('Unknown or missing step type.');
        }
        $actor = \is_string($event['actor'] ?? null) && '' !== $event['actor'] ? $event['actor'] : 'system';
        $content = \is_string($event['content'] ?? null) ? mb_substr($event['content'], 0, self::MAX_CONTENT) : '';
        $metadata = \is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $owner = $run->getOwner();
        $topic = null !== $owner ? $this->realtime->runTopic($owner, $run->getId()->toRfc4122()) : null;

        if (StepType::Token === $type) {
            if (null !== $topic) {
                $this->realtime->publish($topic, 'run.token', ['runId' => $run->getId()->toRfc4122(), 'actor' => $actor, 'delta' => $content]);
            }

            return null;
        }

        $sequence = 1 + (int) $this->em->createQuery('SELECT COALESCE(MAX(s.sequence), 0) FROM App\Entity\RunStep s WHERE s.run = :run')
            ->setParameter('run', $run->getId(), 'uuid')
            ->getSingleScalarResult();

        $step = new RunStep($run, $sequence, $actor, $type, $content, $metadata);
        $this->em->persist($step);
        $this->em->flush();

        if (null !== $topic) {
            $this->realtime->publish($topic, 'run.step', [
                'runId' => $run->getId()->toRfc4122(),
                'id' => $step->getId()->toRfc4122(),
                'sequence' => $step->getSequence(),
                'actor' => $step->getActor(),
                'type' => $step->getType()->value,
                'content' => $step->getContent(),
                'metadata' => $step->getMetadata(),
                'createdAt' => $step->getCreatedAt()->format(\DATE_ATOM),
            ]);
        }

        return $step;
    }
}
