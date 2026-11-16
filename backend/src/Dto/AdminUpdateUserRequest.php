<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminUpdateUserRequest
{
    /** @param list<string>|null $roles */
    public function __construct(
        #[Assert\All([new Assert\Choice(choices: ['ROLE_USER', 'ROLE_ADMIN'])])]
        public ?array $roles = null,
        public ?bool $active = null,
    ) {
    }
}
