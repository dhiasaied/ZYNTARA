<?php

namespace App\Tests\Api;

use App\Entity\Document;
use App\Message\IngestDocument;
use App\MessageHandler\IngestDocumentHandler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class KnowledgeTest extends ApiTestBase
{
    private function fixture(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir().'/zyntara_'.uniqid().'_'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function testUploadIngestAndSearch(): void
    {
        $user = $this->createUser();
        $kb = $this->api('POST', '/api/knowledge_bases', $user, ['json' => ['name' => 'Policies']])->toArray();

        $response = $this->api('POST', '/api/knowledge_bases/'.$kb['id'].'/documents', $user, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => ['files' => ['files' => [
                $this->fixture('returns.md', "# Returns\nWithin 30 days."),
                $this->fixture('virus.exe', 'MZ'),
            ]]],
        ]);
        self::assertResponseStatusCodeSame(202);
        $data = $response->toArray();
        self::assertCount(1, $data['documents']);
        self::assertCount(1, $data['errors']);
        self::assertSame('pending', $data['documents'][0]['status']);

        $messages = $this->queued('async');
        self::assertInstanceOf(IngestDocument::class, $messages[0]);
        static::getContainer()->get(IngestDocumentHandler::class)($messages[0]);

        $this->em()->clear();
        $document = $this->em()->find(Document::class, $data['documents'][0]['id']);
        self::assertSame('indexed', $document->getStatus()->value);
        self::assertSame(3, $document->getChunkCount());

        $kbRead = $this->api('GET', '/api/knowledge_bases/'.$kb['id'], $user)->toArray();
        self::assertSame(1, $kbRead['indexedCount']);

        $search = $this->api('POST', '/api/knowledge_bases/'.$kb['id'].'/search', $user, ['json' => ['query' => 'refund window']]);
        self::assertResponseIsSuccessful();
        self::assertSame('Returns within 30 days', $search->toArray()['results'][0]['content']);

        $this->api('DELETE', '/api/documents/'.$document->getId()->toRfc4122(), $user);
        self::assertResponseStatusCodeSame(204);
    }

    public function testCannotUploadIntoSomeoneElsesKnowledgeBase(): void
    {
        $owner = $this->createUser('owner@example.com');
        $intruder = $this->createUser('intruder@example.com');
        $kb = $this->api('POST', '/api/knowledge_bases', $owner, ['json' => ['name' => 'Private']])->toArray();

        $this->api('POST', '/api/knowledge_bases/'.$kb['id'].'/documents', $intruder, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => ['files' => ['files' => [$this->fixture('a.txt', 'hello')]]],
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
