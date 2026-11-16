<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Crew;
use App\Entity\Run;
use App\Entity\User;
use App\Enum\AgentLevel;
use App\Enum\RunStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Usage analytics. Aggregation is done in PHP over a bounded window so the same code
 * runs on PostgreSQL (prod) and SQLite (tests) without vendor-specific date functions.
 */
final class AnalyticsService
{
    private const MAX_ROWS = 20000;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function overview(?User $scope, int $days): array
    {
        $days = max(1, min(365, $days));
        $since = (new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', $days - 1));

        $qb = $this->em->createQueryBuilder()
            ->select('r.createdAt AS createdAt', 'r.status AS status', 'r.kind AS kind', 'r.tokensIn AS tokensIn', 'r.tokensOut AS tokensOut', 'r.durationMs AS durationMs', 'r.toolsUsed AS toolsUsed', 'IDENTITY(r.agent) AS agentId', 'IDENTITY(r.crew) AS crewId')
            ->from(Run::class, 'r')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->setMaxResults(self::MAX_ROWS);
        if (null !== $scope) {
            $qb->andWhere('r.owner = :owner')->setParameter('owner', $scope->getId(), 'uuid');
        }
        $rows = $qb->getQuery()->getArrayResult();

        $series = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = $since->modify("+$i days")->format('Y-m-d');
            $series[$day] = ['date' => $day, 'runs' => 0, 'succeeded' => 0, 'failed' => 0, 'tokens' => 0];
        }

        $byStatus = array_fill_keys(array_map(static fn (RunStatus $s) => $s->value, RunStatus::cases()), 0);
        $byKind = ['prompt' => 0, 'agent' => 0, 'crew' => 0];
        $tools = [];
        $agentCounts = [];
        $tokensIn = $tokensOut = 0;
        $durations = [];

        foreach ($rows as $row) {
            $status = $row['status'] instanceof RunStatus ? $row['status']->value : (string) $row['status'];
            $kind = \is_object($row['kind']) ? $row['kind']->value : (string) $row['kind'];
            $day = $row['createdAt']->format('Y-m-d');
            $tokens = (int) $row['tokensIn'] + (int) $row['tokensOut'];

            if (isset($series[$day])) {
                ++$series[$day]['runs'];
                $series[$day]['tokens'] += $tokens;
                if ('succeeded' === $status) {
                    ++$series[$day]['succeeded'];
                } elseif ('failed' === $status) {
                    ++$series[$day]['failed'];
                }
            }
            ++$byStatus[$status];
            ++$byKind[$kind];
            $tokensIn += (int) $row['tokensIn'];
            $tokensOut += (int) $row['tokensOut'];
            if (null !== $row['durationMs'] && 'succeeded' === $status) {
                $durations[] = (int) $row['durationMs'];
            }
            foreach ((array) $row['toolsUsed'] as $tool) {
                $tools[$tool] = ($tools[$tool] ?? 0) + 1;
            }
            if (null !== $row['agentId']) {
                $agentCounts[(string) $row['agentId']] = ($agentCounts[(string) $row['agentId']] ?? 0) + 1;
            }
        }

        arsort($tools);
        arsort($agentCounts);
        $finished = $byStatus['succeeded'] + $byStatus['failed'];
        sort($durations);

        return [
            'days' => $days,
            'totals' => [
                'runs' => \count($rows),
                'succeeded' => $byStatus['succeeded'],
                'failed' => $byStatus['failed'],
                'successRate' => $finished > 0 ? round($byStatus['succeeded'] / $finished * 100, 1) : null,
                'tokensIn' => $tokensIn,
                'tokensOut' => $tokensOut,
                'avgDurationMs' => [] !== $durations ? (int) (array_sum($durations) / \count($durations)) : null,
                'p95DurationMs' => [] !== $durations ? $durations[(int) floor(0.95 * (\count($durations) - 1))] : null,
            ],
            'byStatus' => $byStatus,
            'byKind' => $byKind,
            'series' => array_values($series),
            'tools' => array_map(static fn ($k, $v) => ['tool' => $k, 'count' => $v], array_keys($tools), array_values($tools)),
            'topAgents' => $this->topAgents(\array_slice($agentCounts, 0, 5, true)),
            'ladder' => $this->ladder($scope),
        ];
    }

    /**
     * How many agents sit on each rung of the ladder (+ crews as AGENTIC).
     *
     * @return list<array{level: string, count: int}>
     */
    public function ladder(?User $scope): array
    {
        $counts = array_fill_keys(array_map(static fn (AgentLevel $l) => $l->value, AgentLevel::cases()), 0);
        $criteria = null !== $scope ? ['owner' => $scope] : [];

        foreach ($this->em->getRepository(Agent::class)->findBy($criteria) as $agent) {
            ++$counts[$agent->getLevel()->value];
        }
        $counts[AgentLevel::Agentic->value] = $this->em->getRepository(Crew::class)->count($criteria);
        $counts[AgentLevel::Prompt->value] = $this->em->getRepository(Run::class)->count($criteria + ['kind' => 'prompt']);

        return array_map(static fn ($k, $v) => ['level' => $k, 'count' => $v], array_keys($counts), array_values($counts));
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array{id: string, name: string, runs: int}>
     */
    private function topAgents(array $counts): array
    {
        if ([] === $counts) {
            return [];
        }
        $result = [];
        foreach ($counts as $id => $runs) {
            $agent = $this->em->getRepository(Agent::class)->find($id);
            if (null !== $agent) {
                $result[] = ['id' => (string) $id, 'name' => $agent->getName(), 'runs' => $runs];
            }
        }

        return $result;
    }
}
