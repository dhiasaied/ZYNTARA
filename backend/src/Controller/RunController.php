<?php

namespace App\Controller;

use App\Entity\Run;
use App\Entity\User;
use App\Service\RunManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/runs/{id}', requirements: ['id' => Requirement::UUID], priority: 10)]
#[IsGranted('ROLE_USER')]
final class RunController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RunManager $runManager,
    ) {
    }

    #[Route('/cancel', name: 'run_cancel', methods: ['POST'])]
    public function cancel(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $run = $this->findOwned($id, $user);
        $this->runManager->cancel($run);

        return $this->json(['id' => $run->getId()->toRfc4122(), 'status' => $run->getStatus()->value]);
    }

    /** Re-runs the same input against the same target (playground/agent/crew). */
    #[Route('/retry', name: 'run_retry', methods: ['POST'])]
    public function retry(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $original = $this->findOwned($id, $user);
        if (null !== $original->getConversation()) {
            return $this->json(['error' => 'Chat runs are retried by sending the message again.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $run = (new Run())
            ->setKind($original->getKind())
            ->setAgent($original->getAgent())
            ->setCrew($original->getCrew())
            ->setInput($original->getInput())
            ->setProvider($original->getProvider())
            ->setModel($original->getModel())
            ->setTemperature($original->getTemperature());
        $this->runManager->enqueue($run, $user);

        return $this->json(['id' => $run->getId()->toRfc4122(), 'status' => $run->getStatus()->value], Response::HTTP_ACCEPTED);
    }

    private function findOwned(string $id, User $user): Run
    {
        $run = $this->em->find(Run::class, $id);
        if (null === $run || !$run->getOwner()?->getId()->equals($user->getId())) {
            throw $this->createNotFoundException('Run not found.');
        }

        return $run;
    }
}
