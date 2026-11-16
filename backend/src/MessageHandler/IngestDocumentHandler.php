<?php

namespace App\MessageHandler;

use App\Entity\Document;
use App\Enum\NotificationType;
use App\Message\IngestDocument;
use App\Service\AgentServiceClient;
use App\Service\AgentServiceException;
use App\Service\NotificationService;
use App\Service\RealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** RAG ingestion: extract → chunk → embed → store, delegated to the agents service. */
#[AsMessageHandler]
final class IngestDocumentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgentServiceClient $agents,
        private readonly NotificationService $notifications,
        private readonly RealtimePublisher $realtime,
    ) {
    }

    public function __invoke(IngestDocument $message): void
    {
        $document = $this->em->find(Document::class, $message->documentId);
        if (null === $document) {
            return;
        }
        $kb = $document->getKnowledgeBase();
        $owner = $kb->getOwner();

        $document->markProcessing();
        $this->em->flush();
        $this->publish($document);

        try {
            $result = $this->agents->ingest([
                'document_id' => $document->getId()->toRfc4122(),
                'knowledge_base_id' => $kb->getId()->toRfc4122(),
                'path' => $document->getStoredPath(),
                'mime_type' => $document->getMimeType(),
                'filename' => $document->getOriginalName(),
            ]);
            $document->markIndexed((int) ($result['chunks'] ?? 0));
            $this->em->flush();
            $this->publish($document);
            if (null !== $owner) {
                $this->notifications->notify(
                    $owner,
                    NotificationType::DocumentIndexed,
                    \sprintf('"%s" is ready', $document->getOriginalName()),
                    \sprintf('%d chunks indexed in %s.', $document->getChunkCount(), $kb->getName()),
                    '/knowledge/'.$kb->getId()->toRfc4122(),
                );
            }
        } catch (AgentServiceException $e) {
            $document->markFailed($e->getMessage());
            $this->em->flush();
            $this->publish($document);
            if (null !== $owner) {
                $this->notifications->notify(
                    $owner,
                    NotificationType::DocumentFailed,
                    \sprintf('Could not index "%s"', $document->getOriginalName()),
                    $e->getMessage(),
                    '/knowledge/'.$kb->getId()->toRfc4122(),
                );
            }
        }
    }

    private function publish(Document $document): void
    {
        $owner = $document->getKnowledgeBase()->getOwner();
        if (null === $owner) {
            return;
        }
        $this->realtime->publish($this->realtime->userTopic($owner), 'document.status', [
            'id' => $document->getId()->toRfc4122(),
            'knowledgeBaseId' => $document->getKnowledgeBase()->getId()->toRfc4122(),
            'status' => $document->getStatus()->value,
            'chunkCount' => $document->getChunkCount(),
            'error' => $document->getError(),
        ]);
    }
}
