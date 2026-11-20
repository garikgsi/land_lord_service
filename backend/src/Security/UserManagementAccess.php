<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class UserManagementAccess
{
    public function assertCanEdit(User $actor, User $target): void
    {
        $this->assertAdministrator($actor);
        if (!$actor->isSuperAdmin() && $target->isAdmin() && $target->getId() !== $actor->getId()) {
            throw new AccessDeniedHttpException('Изменять другого администратора может только суперадминистратор.');
        }
    }

    /** @param list<string> $roles @param list<string> $previousRoles */
    public function assertCanAssignRoles(User $actor, array $roles, array $previousRoles = []): void
    {
        $this->assertAdministrator($actor);
        if (in_array(Role::SuperAdmin->value, $roles, true) !== in_array(Role::SuperAdmin->value, $previousRoles, true)) {
            throw new AccessDeniedHttpException('Системная роль суперадминистратора назначается только миграцией и не может изменяться.');
        }
        if (!$actor->isSuperAdmin() && in_array(Role::Admin->value, $roles, true) !== in_array(Role::Admin->value, $previousRoles, true)) {
            throw new AccessDeniedHttpException('Назначать и снимать роль администратора может только суперадминистратор.');
        }
    }

    private function assertAdministrator(User $actor): void
    {
        if (!$actor->isAdmin() || !$actor->isActive() || $actor->isBlocked()) {
            throw new AccessDeniedHttpException('Требуются права активного администратора.');
        }
    }
}
