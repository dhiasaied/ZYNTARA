<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SearchRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $query = '',
        #[Assert\Range(min: 1, max: 20)]
        public int $k = 5,
    ) {
    }
}
