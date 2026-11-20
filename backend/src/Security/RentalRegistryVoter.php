<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RentalRegistryVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'RENTAL_REGISTRY_EDIT' === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && ($user->isAdmin() || in_array(Role::Lawyer->value, $user->getRoles(), true));
    }
}
