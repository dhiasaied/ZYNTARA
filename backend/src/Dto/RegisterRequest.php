<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 120)]
        public string $fullName = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Use at least 8 characters.')]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_WEAK)]
        public string $password = '',
    ) {
    }
}
