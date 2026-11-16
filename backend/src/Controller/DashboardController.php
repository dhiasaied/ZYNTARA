<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Crew;
use App\Entity\Document;
use App\Entity\KnowledgeBase;
use App\Entity\Notification;
use App\Entity\Run;
use App\Entity\User;
use App\Enum\RunStatus;
use App\Service\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', priority: 10)]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AnalyticsService $analytics,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(#[CurrentUser] User $user): JsonResponse
    {
        $owner = ['owner' => $user];
        $recent = $this->em->getRepository(Run::class)->findBy($owner, ['createdAt' => 'DESC'], 6);

        $documents = (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')->from(Document::class, 'd')->join('d.knowledgeBase', 'kb')
            ->where('kb.owner = :u')->setParameter('u', $user->getId(), 'uuid')
            ->getQuery()->getSingleScalarResult();

        return $this->json([
            'counts' => [
                'agents' => $this->em->getRepository(Agent::class)->count($owner),
                'crews' => $this->em->getRepository(Crew::class)->count($owner),
                'knowledgeBases' => $this->em->getRepository(KnowledgeBase::class)->count($owner),
                'documents' => $documents,
                'runs' => $this->em->getRepository(Run::class)->count($owner),
                'activeRuns' => $this->em->getRepository(Run::class)->count($owner + ['status' => RunStatus::Running])
                    + $this->em->getRepository(Run::class)->count($owner + ['status' => RunStatus::Queued]),
                'unreadNotifications' => $this->em->getRepository(Notification::class)->count(['recipient' => $user, 'readAt' => null]),
            ],
            'ladder' => $this->analytics->ladder($user),
            'recentRuns' => array_map(static fn (Run $r) => [
                'id' => $r->getId()->toRfc4122(),
                'title' => $r->getTitle(),
                'kind' => $r->getKind()->value,
                'status' => $r->getStatus()->value,
                'input' => mb_substr($r->getInput(), 0, 120),
                'durationMs' => $r->getDurationMs(),
                'createdAt' => $r->getCreatedAt()->format(\DATE_ATOM),
            ], $recent),
            'week' => $this->analytics->overview($user, 7)['series'],
        ]);
    }

    #[Route('/analytics', name: 'analytics', methods: ['GET'])]
    public function analytics(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        return $this->json($this->analytics->overview($user, $request->query->getInt('days', 30)));
    }
}
