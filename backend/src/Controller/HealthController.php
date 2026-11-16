<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Liveness/readiness probe used by Docker healthchecks. */
final class HealthController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');

            return $this->json(['status' => 'ok']);
        } catch (\Throwable) {
            return $this->json(['status' => 'degraded', 'database' => false], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
