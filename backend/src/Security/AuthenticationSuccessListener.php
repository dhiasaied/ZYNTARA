<?php

namespace App\Security;

use App\Controller\UserPresenter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Enriches the login response with the user profile and records the login time. */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class AuthenticationSuccessListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        $user->touchLastLogin();
        $this->em->flush();

        $data = $event->getData();
        $data['user'] = UserPresenter::present($user);
        $event->setData($data);
    }
}
