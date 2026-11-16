<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Stores knowledge-base files on a volume shared with the agents service.
 * Paths stored in the DB are relative to STORAGE_DIR so both containers resolve them.
 */
final class DocumentStorage
{
    private const EXTENSION_MIME = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'markdown' => 'text/markdown',
        'csv' => 'text/csv',
        'html' => 'text/html',
        'htm' => 'text/html',
        'json' => 'application/json',
    ];

    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire('%env(resolve:STORAGE_DIR)%')]
        private readonly string $storageDir,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * Validates and moves an uploaded file.
     *
     * @return array{path: string, mime: string, size: int, name: string}
     *
     * @throws \InvalidArgumentException on rejected files
     */
    public function store(UploadedFile $file, string $knowledgeBaseId): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Upload failed: '.$file->getErrorMessage());
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > Document::MAX_SIZE) {
            throw new \InvalidArgumentException('Files must be between 1 byte and 20 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = self::EXTENSION_MIME[$extension] ?? null;
        $detected = $file->getMimeType() ?? 'application/octet-stream';
        // Trust the content sniffing for PDFs, the extension for text formats (often detected as text/plain).
        if (null === $mime || ('pdf' === $extension && 'application/pdf' !== $detected) || ('pdf' !== $extension && !str_starts_with($detected, 'text/') && 'application/json' !== $detected)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported file type "%s". Allowed: PDF, TXT, MD, CSV, HTML, JSON.', $extension ?: $detected));
        }

        $relativeDir = 'kb/'.$knowledgeBaseId;
        $filename = Uuid::v7()->toRfc4122().'.'.$extension;
        $this->fs->mkdir($this->storageDir.'/'.$relativeDir);
        $file->move($this->storageDir.'/'.$relativeDir, $filename);

        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $file->getClientOriginalName()) ?: $filename;

        return ['path' => $relativeDir.'/'.$filename, 'mime' => $mime, 'size' => $size, 'name' => mb_substr($name, 0, 255)];
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->storageDir.'/'.ltrim($relativePath, '/');
    }

    public function delete(string $relativePath): void
    {
        if (str_contains($relativePath, '..')) {
            return;
        }
        $this->fs->remove($this->absolutePath($relativePath));
    }

    public function deleteKnowledgeBase(string $knowledgeBaseId): void
    {
        if (!Uuid::isValid($knowledgeBaseId)) {
            return;
        }
        $this->fs->remove($this->storageDir.'/kb/'.$knowledgeBaseId);
    }
}
