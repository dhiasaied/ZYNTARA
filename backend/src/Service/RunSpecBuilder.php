<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Crew;
use App\Entity\Message;
use App\Entity\Run;
use App\Enum\RunKind;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates a persisted Run (+ its agent/crew/conversation) into the JSON contract
 * understood by the agents service (agents/app/schemas.py::RunSpec).
 */
final class RunSpecBuilder
{
    private const HISTORY_LIMIT = 20;

    public function __construct(
        #[Autowire(env: 'INTERNAL_CALLBACK_URL')]
        private readonly string $callbackBaseUrl,
        #[Autowire(env: 'INTERNAL_API_TOKEN')]
        private readonly string $internalToken,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(Run $run): array
    {
        $runId = $run->getId()->toRfc4122();
        $spec = [
            'run_id' => $runId,
            'owner_id' => $run->getOwner()?->getId()->toRfc4122(),
            'kind' => $run->getKind()->value,
            'input' => $run->getInput(),
            'history' => $this->history($run),
            'callback' => [
                'url' => rtrim($this->callbackBaseUrl, '/').'/internal/runs/'.$runId.'/events',
                'token' => $this->internalToken,
            ],
        ];

        switch ($run->getKind()) {
            case RunKind::Prompt:
                $spec['prompt'] = [
                    'provider' => $run->getProvider(),
                    'model' => $run->getModel(),
                    'temperature' => $run->getTemperature() ?? 0.7,
                ];
                break;
            case RunKind::Agent:
                $agent = $run->getAgent() ?? throw new \LogicException('Agent run without agent.');
                $spec['agent'] = $this->agent($agent);
                break;
            case RunKind::Crew:
                $crew = $run->getCrew() ?? throw new \LogicException('Crew run without crew.');
                $spec['crew'] = $this->crew($crew);
                break;
        }

        return $spec;
    }

    /** @return array<string, mixed> */
    public function agent(Agent $agent): array
    {
        return [
            'id' => $agent->getId()->toRfc4122(),
            'name' => $agent->getName(),
            'vertical' => $agent->getVertical(),
            'system_prompt' => $agent->getSystemPrompt(),
            'provider' => $agent->getProvider(),
            'model' => $agent->getModel(),
            'temperature' => $agent->getTemperature(),
            'tools' => $agent->getTools(),
            'knowledge_base_ids' => array_values($agent->getKnowledgeBases()->map(
                static fn ($kb) => $kb->getId()->toRfc4122(),
            )->toArray()),
            'autonomous' => $agent->isAutonomous(),
            'max_iterations' => $agent->getMaxIterations(),
            'memory_enabled' => $agent->isMemoryEnabled(),
        ];
    }

    /** @return array<string, mixed> */
    private function crew(Crew $crew): array
    {
        $members = [];
        foreach ($crew->getMembers() as $member) {
            if (null === $member->getAgent()) {
                continue;
            }
            $members[] = ['role' => $member->getRole(), 'agent' => $this->agent($member->getAgent())];
        }

        return [
            'id' => $crew->getId()->toRfc4122(),
            'name' => $crew->getName(),
            'goal' => $crew->getGoal(),
            'strategy' => $crew->getStrategy()->value,
            'max_rounds' => $crew->getMaxRounds(),
            'supervisor' => ['provider' => $crew->getSupervisorProvider(), 'model' => $crew->getSupervisorModel()],
            'members' => $members,
        ];
    }

    /** @return list<array{role: string, content: string}> previous turns, excluding the message being answered */
    private function history(Run $run): array
    {
        $conversation = $run->getConversation();
        if (null === $conversation) {
            return [];
        }
        $history = [];
        foreach ($conversation->getMessages() as $message) {
            if ($message->getRun() === $run && Message::ROLE_USER === $message->getRole()) {
                continue;
            }
            $history[] = ['role' => $message->getRole(), 'content' => $message->getContent()];
        }

        return \array_slice($history, -self::HISTORY_LIMIT);
    }
}
