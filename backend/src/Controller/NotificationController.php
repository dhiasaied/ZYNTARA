<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', priority: 10)]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/unread-count', name: 'notifications_unread', methods: ['GET'])]
    public function unread(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(['count' => $em->getRepository(Notification::class)->count(['recipient' => $user, 'readAt' => null])]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $updated = $em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.readAt', ':now')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();

        return $this->json(['updated' => $updated]);
    }
}
