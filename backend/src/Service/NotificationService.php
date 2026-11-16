<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RealtimePublisher $realtime,
    ) {
    }

    public function notify(User $user, NotificationType $type, string $title, ?string $body = null, ?string $link = null): Notification
    {
        $notification = new Notification($user, $type, $title, $body, $link);
        $this->em->persist($notification);
        $this->em->flush();

        $this->realtime->publish($this->realtime->userTopic($user), 'notification', [
            'id' => $notification->getId()->toRfc4122(),
            'type' => $type->value,
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'link' => $notification->getLink(),
            'read' => false,
            'createdAt' => $notification->getCreatedAt()->format(\DATE_ATOM),
        ]);

        return $notification;
    }
}
