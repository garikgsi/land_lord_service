<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\UniqueConstraint(name: 'uniq_notifications_event', columns: ['user_id', 'deduplication_key'])]
#[ORM\Index(name: 'idx_notifications_user_created', columns: ['user_id', 'created_at', 'id'])]
#[ORM\Index(name: 'idx_notifications_user_read', columns: ['user_id', 'read_at'])]
#[ORM\Index(name: 'idx_notifications_user_due', columns: ['user_id', 'due_date'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
        private User $user,
        #[ORM\Column(length: 80)]
        private string $kind,
        #[ORM\Column(type: Types::TEXT)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $dueDate = null,
        #[ORM\Column(name: 'source_path', length: 255, nullable: true)]
        private ?string $sourcePath = null,
        #[ORM\Column(name: 'deduplication_key', length: 190, nullable: true)]
        private ?string $deduplicationKey = null,
        #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE')]
        private ?\DateTimeImmutable $readAt = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getSourcePath(): ?string
    {
        return $this->sourcePath;
    }
}
