<?php

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OwnedInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assigns the authenticated user as owner on creation, then delegates to Doctrine persistence.
 *
 * @implements ProcessorInterface<OwnedInterface, OwnedInterface>
 */
final class OwnerProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if ($data instanceof OwnedInterface && null === $data->getOwner() && $user instanceof User) {
            $data->setOwner($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
