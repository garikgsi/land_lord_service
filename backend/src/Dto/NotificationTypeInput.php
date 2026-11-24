<?php

declare(strict_types=1);

namespace App\Dto;

use App\Security\Role;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class NotificationTypeInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $name = '',
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual(2147483647)]
        public int $days = 0,
        #[Assert\All([new Assert\Type('string')])]
        public array $roles = [],
        public bool $isActive = true,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        foreach ($this->roles as $role) {
            if (!is_string($role) || null === Role::tryFrom($role)) {
                $context->buildViolation('Неизвестная роль.')->atPath('roles')->addViolation();

                return;
            }
        }
    }
}
