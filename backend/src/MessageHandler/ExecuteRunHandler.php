<?php

namespace App\MessageHandler;

use App\Entity\Run;
use App\Enum\RunStatus;
use App\Message\ExecuteRun;
use App\Service\AgentServiceException;
use App\Service\RunManager;
use App\Service\RunSpecBuilder;
use App\Service\AgentServiceClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Worker side of a run: hand the spec to LangGraph and record the outcome. */
#[AsMessageHandler]
final class ExecuteRunHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgentServiceClient $agents,
        private readonly RunSpecBuilder $specBuilder,
        private readonly RunManager $runManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExecuteRun $message): void
    {
        $run = $this->em->find(Run::class, $message->runId);
        if (null === $run || RunStatus::Queued !== $run->getStatus()) {
            $this->logger->info('Skipping run that is gone or no longer queued', ['run' => $message->runId]);

            return;
        }

        $this->runManager->start($run);
        $startedAt = microtime(true);

        try {
            $result = $this->agents->execute($this->specBuilder->build($run));
        } catch (AgentServiceException $e) {
            $this->em->refresh($run);
            if (!$run->getStatus()->isTerminal()) {
                $this->runManager->fail($run, $e->getMessage());
            }

            return;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error while executing run', ['run' => $message->runId, 'exception' => $e]);
            $this->em->refresh($run);
            if (!$run->getStatus()->isTerminal()) {
                $this->runManager->fail($run, 'Internal error while executing the run.');
            }

            return;
        }

        // Steps were recorded by the web container meanwhile; re-read state (it may have been cancelled).
        $this->em->refresh($run);
        if ($run->getStatus()->isTerminal() || ($result['cancelled'] ?? false)) {
            return;
        }

        $this->runManager->succeed(
            $run,
            (string) ($result['output'] ?? ''),
            (int) ($result['usage']['input_tokens'] ?? 0),
            (int) ($result['usage']['output_tokens'] ?? 0),
            array_values(array_filter((array) ($result['tools_used'] ?? []), 'is_string')),
        );
        $this->logger->info('Run succeeded', ['run' => $message->runId, 'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000)]);
    }
}
