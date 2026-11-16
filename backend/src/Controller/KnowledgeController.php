<?php

namespace App\Controller;

use App\Dto\SearchRequest;
use App\Entity\Document;
use App\Entity\KnowledgeBase;
use App\Entity\User;
use App\Message\IngestDocument;
use App\Service\AgentServiceClient;
use App\Service\AgentServiceException;
use App\Service\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** File upload, re-indexing and retrieval testing for knowledge bases (RAG). */
#[Route('/api', priority: 10)]
#[IsGranted('ROLE_USER')]
final class KnowledgeController extends AbstractController
{
    private const MAX_FILES_PER_UPLOAD = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/knowledge_bases/{id}/documents', name: 'kb_upload', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function upload(string $id, Request $request, #[CurrentUser] User $user, DocumentStorage $storage): JsonResponse
    {
        $kb = $this->findKnowledgeBase($id, $user);
        $files = $request->files->all('files');
        if ([] === $files && $request->files->get('file') instanceof UploadedFile) {
            $files = [$request->files->get('file')];
        }
        $files = array_values(array_filter($files, static fn ($f) => $f instanceof UploadedFile));
        if ([] === $files) {
            return $this->json(['error' => 'Attach at least one file in the "files[]" field.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (\count($files) > self::MAX_FILES_PER_UPLOAD) {
            return $this->json(['error' => \sprintf('Upload at most %d files at once.', self::MAX_FILES_PER_UPLOAD)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $created = [];
        $errors = [];
        foreach ($files as $file) {
            try {
                $stored = $storage->store($file, $kb->getId()->toRfc4122());
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['file' => $file->getClientOriginalName(), 'error' => $e->getMessage()];
                continue;
            }
            $document = new Document($kb, $stored['name'], $stored['path'], $stored['mime'], $stored['size']);
            $this->em->persist($document);
            $created[] = $document;
        }
        $this->em->flush();

        foreach ($created as $document) {
            $this->bus->dispatch(new IngestDocument($document->getId()->toRfc4122()));
        }

        return $this->json([
            'documents' => array_map(static fn (Document $d) => [
                'id' => $d->getId()->toRfc4122(),
                'originalName' => $d->getOriginalName(),
                'mimeType' => $d->getMimeType(),
                'size' => $d->getSize(),
                'status' => $d->getStatus()->value,
                'chunkCount' => $d->getChunkCount(),
                'createdAt' => $d->getCreatedAt()->format(\DATE_ATOM),
            ], $created),
            'errors' => $errors,
        ], [] === $created ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_ACCEPTED);
    }

    #[Route('/documents/{id}/reindex', name: 'document_reindex', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function reindex(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $document = $this->em->find(Document::class, $id);
        if (null === $document || !$document->getKnowledgeBase()->getOwner()?->getId()->equals($user->getId())) {
            throw $this->createNotFoundException('Document not found.');
        }
        $this->bus->dispatch(new IngestDocument($document->getId()->toRfc4122()));

        return $this->json(['id' => $id, 'status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    /** "Only from them": lets the user check what the agent would retrieve for a question. */
    #[Route('/knowledge_bases/{id}/search', name: 'kb_search', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function search(string $id, #[CurrentUser] User $user, #[MapRequestPayload] SearchRequest $payload, AgentServiceClient $agents): JsonResponse
    {
        $kb = $this->findKnowledgeBase($id, $user);
        try {
            $result = $agents->search([$kb->getId()->toRfc4122()], $payload->query, $payload->k);
        } catch (AgentServiceException $e) {
            return $this->json(['error' => 'Retrieval service unavailable: '.$e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json($result);
    }

    private function findKnowledgeBase(string $id, User $user): KnowledgeBase
    {
        $kb = $this->em->find(KnowledgeBase::class, $id);
        if (null === $kb || !$kb->getOwner()?->getId()->equals($user->getId())) {
            throw $this->createNotFoundException('Knowledge base not found.');
        }

        return $kb;
    }
}
