<?php

namespace App\Controller;

use App\Dto\ChangePasswordRequest;
use App\Dto\RegisterRequest;
use App\Dto\UpdateProfileRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Registration and self-service profile. Login itself is handled by json_login + Lexik JWT. */
#[Route('/api', priority: 10)]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $payload,
        Request $request,
        UserRepository $users,
        JWTTokenManagerInterface $jwt,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactoryInterface $limiter,
    ): JsonResponse {
        if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many registrations from this address. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        if (null !== $users->findOneBy(['email' => mb_strtolower(trim($payload->email))])) {
            return $this->json(['error' => 'An account with this email already exists.', 'violations' => [['propertyPath' => 'email', 'message' => 'An account with this email already exists.']]], Response::HTTP_CONFLICT);
        }

        $user = (new User())->setEmail($payload->email)->setFullName($payload->fullName);
        $user->setPassword($this->hasher->hashPassword($user, $payload->password));
        // The very first account of an installation becomes the administrator.
        if (0 === $users->count([])) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $user->touchLastLogin();
        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['token' => $jwt->create($user), 'user' => UserPresenter::present($user)], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(UserPresenter::present($user));
    }

    #[Route('/me', name: 'me_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(#[CurrentUser] User $user, #[MapRequestPayload] UpdateProfileRequest $payload): JsonResponse
    {
        if (null !== $payload->fullName) {
            $user->setFullName($payload->fullName);
        }
        if (null !== $payload->preferences) {
            $user->setPreferences($payload->preferences);
        }
        $this->em->flush();

        return $this->json(UserPresenter::present($user));
    }

    #[Route('/me/password', name: 'me_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(#[CurrentUser] User $user, #[MapRequestPayload] ChangePasswordRequest $payload): JsonResponse
    {
        if (!$this->hasher->isPasswordValid($user, $payload->currentPassword)) {
            return $this->json(['error' => 'Current password is incorrect.', 'violations' => [['propertyPath' => 'currentPassword', 'message' => 'Current password is incorrect.']]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->setPassword($this->hasher->hashPassword($user, $payload->newPassword));
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
