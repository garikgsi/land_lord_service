<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class UserView
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    /** @return array{id:int,email:string,firstName:string|null,lastName:?string,roles:list<string>,phone:?string,position:?string,avatarUrl:?string,blocked:bool,active:bool,createdAt:string} */
    public function present(User $user): array
    {
        $id = $user->getId();
        if (null === $id) {
            throw new \LogicException('Пользователь ещё не сохранён.');
        }

        return [
            'id' => $id,
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getAssignedRoles(),
            'phone' => $user->getPhone(),
            'position' => $user->getPosition(),
            'avatarUrl' => null === $user->getAvatar() ? null : $this->urls->generate('user_avatar', ['id' => $id, 'v' => $user->getAvatar()]),
            'blocked' => $user->isBlocked(),
            'active' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
