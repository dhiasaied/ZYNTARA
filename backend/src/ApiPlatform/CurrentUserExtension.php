<?php

namespace App\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Document;
use App\Entity\Notification;
use App\Entity\OwnedInterface;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Row-level multi-tenancy: every API Platform query is restricted to rows owned by the
 * authenticated user. Foreign rows are therefore invisible (404), never just forbidden.
 */
final class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function restrict(QueryBuilder $qb, QueryNameGeneratorInterface $names, string $resourceClass): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $root = $qb->getRootAliases()[0];
        $param = $names->generateParameterName('current_user');

        if (Document::class === $resourceClass) {
            $kb = $names->generateJoinAlias('kb');
            $qb->innerJoin(\sprintf('%s.knowledgeBase', $root), $kb)
                ->andWhere(\sprintf('%s.owner = :%s', $kb, $param))
                ->setParameter($param, $user->getId(), 'uuid');

            return;
        }

        if (!is_a($resourceClass, OwnedInterface::class, true)) {
            return;
        }

        $field = Notification::class === $resourceClass ? 'recipient' : 'owner';
        $qb->andWhere(\sprintf('%s.%s = :%s', $root, $field, $param))
            ->setParameter($param, $user->getId(), 'uuid');
    }
}
