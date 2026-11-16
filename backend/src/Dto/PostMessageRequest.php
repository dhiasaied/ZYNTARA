<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PostMessageRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 20000)]
        public string $content = '',
    ) {
    }
}
