<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Run;
use App\Entity\User;
use App\Service\RunManager;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * POST /api/runs — persists a queued run and hands it to the async worker.
 *
 * @implements ProcessorInterface<Run, Run>
 */
final class RunCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RunManager $runManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Run
    {
        \assert($data instanceof Run);
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return $this->runManager->enqueue($data, $user);
    }
}
