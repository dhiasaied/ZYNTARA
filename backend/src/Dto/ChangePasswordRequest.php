<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096)]
        #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'Choose a password different from the current one.')]
        public string $newPassword = '',
    ) {
    }
}
