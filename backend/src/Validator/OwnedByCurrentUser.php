<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** The referenced resource (agent, knowledge base, crew...) must belong to the authenticated user. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class OwnedByCurrentUser extends Constraint
{
    public string $message = 'This resource does not exist or does not belong to you.';
}
