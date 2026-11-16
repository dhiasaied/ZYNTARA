<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\RealtimePublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Issues a short-lived Mercure subscriber JWT limited to the caller's own topics. */
#[Route('/api/realtime', priority: 10)]
#[IsGranted('ROLE_USER')]
final class RealtimeController extends AbstractController
{
    private const TTL = 3600;

    #[Route('/token', name: 'realtime_token', methods: ['GET'])]
    public function token(
        #[CurrentUser] User $user,
        HubInterface $hub,
        RealtimePublisher $realtime,
        #[Autowire(env: 'bool:MERCURE_ENABLED')] bool $enabled,
    ): JsonResponse {
        if (!$enabled) {
            return $this->json(['enabled' => false]);
        }
        $topics = $realtime->subscriberTopics($user);
        $factory = $hub->getFactory() ?? throw new \LogicException('Mercure token factory is not configured.');

        return $this->json([
            'enabled' => true,
            'hubUrl' => $hub->getPublicUrl(),
            'token' => $factory->create($topics, [], ['exp' => new \DateTimeImmutable('+'.self::TTL.' seconds')]),
            'topics' => $topics,
            'userTopic' => $realtime->userTopic($user),
            'expiresIn' => self::TTL,
        ]);
    }
}
