<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MeterVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['METER_EDIT', 'METER_READING_EDIT', 'METER_INSTALL'], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && ($user->isAdmin() || (in_array($attribute, ['METER_READING_EDIT', 'METER_INSTALL'], true) && in_array(Role::Technician->value, $user->getRoles(), true)));
    }
}
