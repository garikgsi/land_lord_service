<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use App\Security\Role;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const EMAIL_MAX_LENGTH = 254;
    public const NAME_MAX_LENGTH = 100;
    public const PHONE_MAX_LENGTH = 50;
    public const POSITION_MAX_LENGTH = 160;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::EMAIL_MAX_LENGTH, unique: true)]
    private string $email;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column]
    #[Ignore]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: self::PHONE_MAX_LENGTH, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: self::POSITION_MAX_LENGTH, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private bool $blocked = false;

    #[ORM\Column]
    private bool $active = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email)
    {
        $this->setEmail($email);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = mb_strtolower(trim($email));
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = null === $firstName || '' === trim($firstName) ? null : trim($firstName);
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = null === $lastName || '' === trim($lastName) ? null : trim($lastName);
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    #[Ignore]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(#[\SensitiveParameter] string $password): void
    {
        $this->password = $password;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @return list<string> */
    public function getAssignedRoles(): array
    {
        return $this->roles;
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        if (in_array(Role::SuperAdmin->value, $roles, true)) {
            $roles[] = Role::Admin->value;
        }
        $this->roles = array_values(array_unique($roles));
    }

    public function isAdmin(): bool
    {
        return in_array(Role::Admin->value, $this->roles, true) || $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return in_array(Role::SuperAdmin->value, $this->roles, true);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = null === $phone || '' === trim($phone) ? null : trim($phone);
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): void
    {
        $this->position = null === $position || '' === trim($position) ? null : trim($position);
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): void
    {
        $this->blocked = $blocked;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
