<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contract_files')]
#[ORM\UniqueConstraint(name: 'contract_files_storage_name_key', columns: ['storage_name'])]
#[ORM\Index(name: 'idx_contract_files_contract_id', columns: ['contract_id'])]
class ContractFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'RESTRICT')]
        private Contract $contract,
        #[ORM\Column(name: 'original_name', type: Types::TEXT)]
        private string $originalName,
        #[ORM\Column(name: 'storage_name', type: Types::TEXT)]
        private string $storageName,
        #[ORM\Column(name: 'mime_type', type: Types::TEXT)]
        private string $mimeType,
        #[ORM\Column(name: 'size_bytes', type: Types::INTEGER)]
        private int $sizeBytes,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }

    public function setContract(Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function setStorageName(string $storageName): void
    {
        $this->storageName = $storageName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): void
    {
        $this->sizeBytes = $sizeBytes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
