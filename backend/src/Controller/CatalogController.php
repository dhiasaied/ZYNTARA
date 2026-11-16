<?php

namespace App\Controller;

use App\Service\AgentServiceClient;
use App\Service\TemplateCatalog;
use App\Service\ToolCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/catalog', priority: 10)]
#[IsGranted('ROLE_USER')]
final class CatalogController extends AbstractController
{
    #[Route('/tools', name: 'catalog_tools', methods: ['GET'])]
    public function tools(ToolCatalog $catalog): JsonResponse
    {
        return $this->json($catalog->all());
    }

    #[Route('/templates', name: 'catalog_templates', methods: ['GET'])]
    public function templates(TemplateCatalog $catalog): JsonResponse
    {
        return $this->json($catalog->all());
    }

    /** LLM providers and their availability (API key configured / Ollama reachable). */
    #[Route('/providers', name: 'catalog_providers', methods: ['GET'])]
    public function providers(AgentServiceClient $agents): JsonResponse
    {
        return $this->json($agents->providers());
    }
}
