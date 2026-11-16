<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Run;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\RunKind;
use App\Message\ExecuteRun;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** Owns every state transition of a Run and its side effects (queue, realtime, notifications). */
final class RunManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly RealtimePublisher $realtime,
        private readonly NotificationService $notifications,
        private readonly AgentServiceClient $agents,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'limiter.run_creation')]
        private readonly RateLimiterFactoryInterface $runCreationLimiter,
    ) {
    }

    public function enqueue(Run $run, User $user): Run
    {
        $limit = $this->runCreationLimiter->create($user->getId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Too many runs, slow down a little.');
        }

        $run->setOwner($user);
        if (RunKind::Agent !== $run->getKind()) {
            $run->setAgent(null);
        }
        if (RunKind::Crew !== $run->getKind()) {
            $run->setCrew(null);
        }

        $this->em->persist($run);
        $this->em->flush();

        $this->publishStatus($run);
        $this->bus->dispatch(new ExecuteRun($run->getId()->toRfc4122()));
        $this->logger->info('Run queued', ['run' => $run->getId()->toRfc4122(), 'kind' => $run->getKind()->value]);

        return $run;
    }

    public function start(Run $run): void
    {
        $run->markRunning();
        $this->em->flush();
        $this->publishStatus($run);
    }

    /** @param list<string> $toolsUsed */
    public function succeed(Run $run, string $output, int $tokensIn, int $tokensOut, array $toolsUsed): void
    {
        $run->markSucceeded($output, $tokensIn, $tokensOut, $toolsUsed);
        if (null !== $conversation = $run->getConversation()) {
            $this->em->persist(new Message($conversation, Message::ROLE_ASSISTANT, $output, $run));
        }
        $this->em->flush();
        $this->publishStatus($run);

        // Chat replies are visible in the chat itself; notify only for background work.
        if (null === $run->getConversation() && null !== $owner = $run->getOwner()) {
            $this->notifications->notify(
                $owner,
                NotificationType::RunSucceeded,
                \sprintf('%s finished', $run->getTitle()),
                mb_substr($output, 0, 180),
                '/runs/'.$run->getId()->toRfc4122(),
            );
        }
    }

    public function fail(Run $run, string $error): void
    {
        $run->markFailed($error);
        if (null !== $conversation = $run->getConversation()) {
            $this->em->persist(new Message($conversation, Message::ROLE_ASSISTANT, '⚠️ The run failed: '.$error, $run));
        }
        $this->em->flush();
        $this->publishStatus($run);

        if (null !== $owner = $run->getOwner()) {
            $this->notifications->notify(
                $owner,
                NotificationType::RunFailed,
                \sprintf('%s failed', $run->getTitle()),
                mb_substr($error, 0, 180),
                '/runs/'.$run->getId()->toRfc4122(),
            );
        }
        $this->logger->warning('Run failed', ['run' => $run->getId()->toRfc4122(), 'error' => $error]);
    }

    public function cancel(Run $run): void
    {
        if ($run->getStatus()->isTerminal()) {
            return;
        }
        $run->markCancelled();
        $this->em->flush();
        $this->agents->cancel($run->getId()->toRfc4122());
        $this->publishStatus($run);
    }

    public function publishStatus(Run $run): void
    {
        if (null === $owner = $run->getOwner()) {
            return;
        }
        $payload = [
            'id' => $run->getId()->toRfc4122(),
            'status' => $run->getStatus()->value,
            'kind' => $run->getKind()->value,
            'title' => $run->getTitle(),
            'output' => $run->getOutput(),
            'error' => $run->getError(),
            'tokensIn' => $run->getTokensIn(),
            'tokensOut' => $run->getTokensOut(),
            'durationMs' => $run->getDurationMs(),
            'toolsUsed' => $run->getToolsUsed(),
            'conversationId' => $run->getConversation()?->getId()->toRfc4122(),
        ];
        $this->realtime->publish($this->realtime->runTopic($owner, $payload['id']), 'run.status', $payload);
        $this->realtime->publish($this->realtime->userTopic($owner), 'run.status', $payload);
    }
}
