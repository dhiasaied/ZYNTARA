<?php

namespace App\Validator;

use App\Entity\OwnedInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class OwnedByCurrentUserValidator extends ConstraintValidator
{
    public function __construct(private readonly Security $security)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OwnedByCurrentUser) {
            throw new UnexpectedTypeException($constraint, OwnedByCurrentUser::class);
        }
        if (null === $value) {
            return;
        }
        if (!$value instanceof OwnedInterface) {
            throw new UnexpectedTypeException($value, OwnedInterface::class);
        }

        $user = $this->security->getUser();
        // CLI / worker context (fixtures, background jobs) has no user: nothing to enforce there.
        if (!$user instanceof User) {
            return;
        }

        if (!$value->getOwner()?->getId()->equals($user->getId())) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
