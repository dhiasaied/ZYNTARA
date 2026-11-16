<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Document;
use App\Message\DeleteVectors;
use App\Service\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<Document, null> */
final class DocumentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentStorage $storage,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof Document);
        $documentId = $data->getId()->toRfc4122();
        $path = $data->getStoredPath();

        $data->getKnowledgeBase()->getDocuments()->removeElement($data);
        $this->em->remove($data);
        $this->em->flush();

        $this->storage->delete($path);
        $this->bus->dispatch(new DeleteVectors(documentId: $documentId));

        return null;
    }
}
