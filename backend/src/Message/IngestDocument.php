<?php

namespace App\Message;

final readonly class IngestDocument
{
    public function __construct(public string $documentId)
    {
    }
}
