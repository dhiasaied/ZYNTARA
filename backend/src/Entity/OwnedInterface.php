<?php

namespace App\Entity;

/**
 * Resources that belong to exactly one user. Enforced at query level by
 * App\ApiPlatform\CurrentUserExtension and on write by App\ApiPlatform\OwnerProcessor.
 */
interface OwnedInterface
{
    public function getOwner(): ?User;

    public function setOwner(User $owner): static;
}
