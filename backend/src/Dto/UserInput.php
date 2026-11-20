<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Security\Role;
use App\Validator\PhoneNumber;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['email', 'firstName'])]
final readonly class UserInput
{
    /** @param list<string> $roles */
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(format: 'email')]
        #[Assert\Email]
        #[Assert\Length(max: User::EMAIL_MAX_LENGTH)]
        public string $email,
        #[OA\Property(description: 'Совмещаемые роли. ROLE_ADMIN назначает/снимает только суперадминистратор. ROLE_SUPER_ADMIN неизменяема и передаётся без изменения при редактировании системной записи.')]
        #[Assert\Choice(callback: [Role::class, 'values'], multiple: true)]
        public array $roles = [],
        #[PhoneNumber]
        #[Assert\Length(max: User::PHONE_MAX_LENGTH)]
        public ?string $phone = null,
        #[Assert\Length(max: User::POSITION_MAX_LENGTH)]
        public ?string $position = null,
        public bool $blocked = false,
        #[OA\Property(description: 'Обязательное непустое имя; пробелы по краям не заменяют имя.', nullable: false, minLength: 1)]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: User::NAME_MAX_LENGTH)]
        public string $firstName = '',
        #[Assert\Length(max: User::NAME_MAX_LENGTH)]
        public ?string $lastName = null,
    ) {
    }
}
