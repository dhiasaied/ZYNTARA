<?php

namespace App\Controller;

use App\Dto\AdminUpdateUserRequest;
use App\Entity\Agent;
use App\Entity\Run;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Service\AgentServiceClient;
use App\Service\AnalyticsService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', priority: 10)]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $qb = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->orderBy('u.createdAt', 'DESC');
        if ('' !== $search = trim((string) $request->query->get('q', ''))) {
            $qb->where('LOWER(u.email) LIKE :q OR LOWER(u.fullName) LIKE :q')->setParameter('q', '%'.mb_strtolower($search).'%');
        }
        $total = (int) (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        $items = array_map(function (User $u): array {
            return UserPresenter::present($u) + [
                'agents' => $this->em->getRepository(Agent::class)->count(['owner' => $u]),
                'runs' => $this->em->getRepository(Run::class)->count(['owner' => $u]),
            ];
        }, $users);

        return $this->json(['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }

    #[Route('/users/{id}', name: 'admin_user_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function updateUser(string $id, #[CurrentUser] User $admin, #[MapRequestPayload] AdminUpdateUserRequest $payload, NotificationService $notifications): JsonResponse
    {
        $user = $this->em->find(User::class, $id) ?? throw $this->createNotFoundException('User not found.');
        $isSelf = $user->getId()->equals($admin->getId());

        if (null !== $payload->roles) {
            if ($isSelf && !\in_array('ROLE_ADMIN', $payload->roles, true)) {
                return $this->json(['error' => 'You cannot remove your own administrator role.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setRoles($payload->roles);
        }
        if (null !== $payload->active) {
            if ($isSelf && !$payload->active) {
                return $this->json(['error' => 'You cannot deactivate your own account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setActive($payload->active);
        }
        $this->em->flush();

        if (null !== $payload->roles) {
            $notifications->notify($user, NotificationType::System, 'Your permissions changed', 'Roles: '.implode(', ', $user->getRoles()));
        }

        return $this->json(UserPresenter::present($user));
    }

    #[Route('/runs', name: 'admin_runs', methods: ['GET'])]
    public function runs(Request $request): JsonResponse
    {
        $qb = $this->em->createQueryBuilder()->select('r', 'o')->from(Run::class, 'r')->join('r.owner', 'o')->orderBy('r.createdAt', 'DESC')->setMaxResults(50);
        if (null !== $status = $request->query->get('status')) {
            $qb->andWhere('r.status = :s')->setParameter('s', $status);
        }

        return $this->json(array_map(static fn (Run $r) => [
            'id' => $r->getId()->toRfc4122(),
            'title' => $r->getTitle(),
            'kind' => $r->getKind()->value,
            'status' => $r->getStatus()->value,
            'owner' => $r->getOwner()?->getEmail(),
            'tokens' => $r->getTokensIn() + $r->getTokensOut(),
            'durationMs' => $r->getDurationMs(),
            'error' => $r->getError(),
            'createdAt' => $r->getCreatedAt()->format(\DATE_ATOM),
        ], $qb->getQuery()->getResult()));
    }

    #[Route('/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function analytics(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $data = $analytics->overview(null, $request->query->getInt('days', 30));
        $data['users'] = $this->em->getRepository(User::class)->count([]);

        return $this->json($data);
    }

    /** Health of every moving part: database, queue, agents service, realtime hub. */
    #[Route('/health', name: 'admin_health', methods: ['GET'])]
    public function health(
        AgentServiceClient $agents,
        HttpClientInterface $httpClient,
        #[Autowire(env: 'MERCURE_URL')] string $mercureUrl,
    ): JsonResponse {
        $checks = [];

        $start = microtime(true);
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            $checks['database'] = ['ok' => true, 'latency_ms' => (int) ((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $pending = (int) $this->em->getRepository(Run::class)->count(['status' => 'queued']);
            $checks['queue'] = ['ok' => true, 'queued_runs' => $pending];
        } catch (\Throwable $e) {
            $checks['queue'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        $checks['agents'] = $agents->health();

        $start = microtime(true);
        try {
            // The hub answers 400/401 to an anonymous GET without topic: reachable is what matters.
            $status = $httpClient->request('GET', $mercureUrl, ['timeout' => 3])->getStatusCode();
            $checks['realtime'] = ['ok' => $status < 500, 'status' => $status, 'latency_ms' => (int) ((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            $checks['realtime'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        $checks['providers'] = ['ok' => true, 'items' => $agents->providers()];

        return $this->json([
            'ok' => $checks['database']['ok'] && $checks['agents']['ok'],
            'checks' => $checks,
            'time' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}
