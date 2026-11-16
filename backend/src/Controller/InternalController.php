<?php

namespace App\Controller;

use App\Entity\Run;
use App\Service\RunEventRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Service-to-service callbacks from the agents service. Not exposed through the public
 * reverse proxy; additionally protected by a shared secret compared in constant time.
 */
#[Route('/internal')]
final class InternalController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'INTERNAL_API_TOKEN')]
        private readonly string $internalToken,
    ) {
    }

    #[Route('/runs/{id}/events', name: 'internal_run_events', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function runEvents(string $id, Request $request, EntityManagerInterface $em, RunEventRecorder $recorder, LoggerInterface $logger): JsonResponse
    {
        if (!$this->authorized($request)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        $run = $em->find(Run::class, $id) ?? throw $this->createNotFoundException('Run not found.');

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }
        $events = isset($payload['events']) && \is_array($payload['events']) ? $payload['events'] : [$payload];

        $recorded = 0;
        foreach ($events as $event) {
            if (!\is_array($event)) {
                continue;
            }
            try {
                if (null !== $recorder->record($run, $event)) {
                    ++$recorded;
                }
            } catch (\InvalidArgumentException $e) {
                $logger->warning('Dropped malformed run event', ['run' => $id, 'error' => $e->getMessage()]);
            }
        }

        // Tells the graph to stop early when the user cancelled.
        return $this->json(['recorded' => $recorded, 'status' => $run->getStatus()->value]);
    }

    private function authorized(Request $request): bool
    {
        $provided = (string) $request->headers->get('X-Internal-Token', '');

        return '' !== $this->internalToken && hash_equals($this->internalToken, $provided);
    }
}
