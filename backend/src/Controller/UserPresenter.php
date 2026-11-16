<?php

namespace App\Controller;

use App\Entity\User;

/** Single JSON shape for a user across auth, profile and admin endpoints. */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public static function present(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'isAdmin' => $user->isAdmin(),
            'active' => $user->isActive(),
            'preferences' => $user->getPreferences(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
        ];
    }
}
