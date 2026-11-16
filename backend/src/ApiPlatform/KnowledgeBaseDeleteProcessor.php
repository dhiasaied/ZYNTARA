<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\KnowledgeBase;
use App\Message\DeleteVectors;
use App\Service\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<KnowledgeBase, null> */
final class KnowledgeBaseDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentStorage $storage,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof KnowledgeBase);
        $kbId = $data->getId()->toRfc4122();

        $this->em->remove($data); // documents cascade at DB level (ON DELETE CASCADE)
        $this->em->flush();

        $this->storage->deleteKnowledgeBase($kbId);
        $this->bus->dispatch(new DeleteVectors(knowledgeBaseId: $kbId));

        return null;
    }
}
