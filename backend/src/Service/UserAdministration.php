<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UserInput;
use App\Entity\User;
use App\Security\Role;
use App\Security\UserManagementAccess;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class UserAdministration
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserManagementAccess $access,
        private TokenService $tokens,
    ) {
    }

    public function create(UserInput $input, User $actor): User
    {
        return $this->em->wrapInTransaction(function () use ($input, $actor): User {
            $this->em->lock($actor, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($actor);
            $this->access->assertCanAssignRoles($actor, $input->roles);
            $user = new User($input->email);
            $this->applyProfile($user, $input);
            $this->em->persist($user);

            return $user;
        });
    }

    public function update(User $user, UserInput $input, User $actor): User
    {
        return $this->em->wrapInTransaction(function () use ($user, $input, $actor): User {
            // Lock in a stable order so concurrent edits cannot remove both final administrators.
            $users = $this->em->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.id')
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->setHint(Query::HINT_REFRESH, true)
                ->getResult();
            $this->access->assertCanEdit($actor, $user);
            if ($actor->getId() === $user->getId() && $input->blocked) {
                throw new ConflictHttpException('Нельзя заблокировать собственную учётную запись.');
            }
            $this->access->assertCanAssignRoles($actor, $input->roles, $user->getAssignedRoles());
            $hadActiveSuperAdmin = array_any($users, static fn (User $candidate): bool => $candidate->isSuperAdmin() && $candidate->isActive() && !$candidate->isBlocked());
            $incomingRoles = $input->roles;
            if (in_array(Role::SuperAdmin->value, $incomingRoles, true)) {
                $incomingRoles[] = Role::Admin->value;
            }
            $rolesChanged = [] !== array_diff($user->getAssignedRoles(), $incomingRoles) || [] !== array_diff($incomingRoles, $user->getAssignedRoles());
            $credentialsChanged = $user->getEmail() !== mb_strtolower(trim($input->email))
                || $rolesChanged
                || $user->isBlocked() !== $input->blocked;
            $this->applyProfile($user, $input);
            if (!array_any($users, static fn (User $candidate): bool => $candidate->isAdmin() && $candidate->isActive() && !$candidate->isBlocked())) {
                throw new ConflictHttpException('Нельзя заблокировать или лишить прав последнего активного администратора.');
            }
            if ($hadActiveSuperAdmin && !array_any($users, static fn (User $candidate): bool => $candidate->isSuperAdmin() && $candidate->isActive() && !$candidate->isBlocked())) {
                throw new ConflictHttpException('Нельзя лишить прав последнего активного суперадминистратора.');
            }
            if ($credentialsChanged) {
                $this->tokens->revokeAll($user);
            }

            return $user;
        });
    }

    private function applyProfile(User $user, UserInput $input): void
    {
        $user->setEmail($input->email);
        $user->setFirstName($input->firstName);
        $user->setLastName($input->lastName);
        $user->setRoles($input->roles);
        $user->setPhone($input->phone);
        $user->setPosition($input->position);
        $user->setBlocked($input->blocked);
    }
}
