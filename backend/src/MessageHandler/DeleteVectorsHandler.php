<?php

namespace App\MessageHandler;

use App\Message\DeleteVectors;
use App\Service\AgentServiceClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteVectorsHandler
{
    public function __construct(private readonly AgentServiceClient $agents)
    {
    }

    /** Failures throw so Messenger retries (vector deletion must eventually happen). */
    public function __invoke(DeleteVectors $message): void
    {
        $this->agents->deleteVectors($message->documentId, $message->knowledgeBaseId);
    }
}
