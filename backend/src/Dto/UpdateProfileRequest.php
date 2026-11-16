<?php

namespace App\Dto;

use App\Entity\Agent;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProfileRequest
{
    /**
     * @param array<string, mixed>|null $preferences
     */
    public function __construct(
        #[Assert\Length(min: 2, max: 120)]
        public ?string $fullName = null,
        #[Assert\Collection(
            fields: [
                'defaultProvider' => new Assert\Optional([new Assert\Choice(choices: Agent::PROVIDERS)]),
                'defaultModel' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 120)]),
                'toastNotifications' => new Assert\Optional([new Assert\Type('bool')]),
                'theme' => new Assert\Optional([new Assert\Choice(choices: ['dark', 'light', 'system'])]),
            ],
            allowExtraFields: false,
        )]
        public ?array $preferences = null,
    ) {
    }
}
