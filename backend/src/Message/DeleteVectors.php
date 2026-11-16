<?php

namespace App\Message;

/** Removes embeddings for a single document or a whole knowledge base from the vector store. */
final readonly class DeleteVectors
{
    public function __construct(
        public ?string $documentId = null,
        public ?string $knowledgeBaseId = null,
    ) {
    }
}
