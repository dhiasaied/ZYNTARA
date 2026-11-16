<?php

namespace App\Repository;

use App\Entity\KnowledgeBase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<KnowledgeBase> */
class KnowledgeBaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KnowledgeBase::class);
    }
}
