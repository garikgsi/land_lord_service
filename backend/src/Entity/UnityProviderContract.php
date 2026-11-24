<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'unity_provider_contracts')]
#[ORM\Index(name: 'idx_unity_provider_contracts_object', columns: ['object_id'])]
#[ORM\Index(name: 'idx_unity_provider_contracts_provider', columns: ['unity_provider_id'])]
#[ORM\Index(name: 'idx_unity_provider_contracts_expiry', columns: ['expiry_date'])]
class UnityProviderContract implements VersionedEntity
{
    use HasDeletion;

    use HasValidity;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Unity> */
    #[ORM\ManyToMany(targetEntity: Unity::class)]
    #[ORM\JoinTable(name: 'unity_provider_contract_unities')]
    #[ORM\JoinColumn(name: 'contract_id', onDelete: 'RESTRICT')]
    #[ORM\InverseJoinColumn(name: 'unity_id', onDelete: 'RESTRICT')]
    private Collection $unities;

    // Relationship-only edits must also archive the preceding contract version.
    #[ORM\Column(name: 'unity_ids', type: Types::JSONB, nullable: true)]
    private ?array $unityIdsSnapshot = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'object_id', nullable: false, onDelete: 'RESTRICT')]
        private RentalObject $object,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'unity_provider_id', nullable: false, onDelete: 'RESTRICT')]
        private UnityProvider $unityProvider,
        #[ORM\Column(name: 'expiry_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $expiryDate,
        #[ORM\Column(name: 'contract_num', type: Types::TEXT, nullable: true)]
        private ?string $contractNum = null,
        #[ORM\Column(name: 'contract_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $contractDate = null,
        #[ORM\Column(name: 'is_suspended')]
        private bool $isSuspended = false,
    ) {
        $this->unities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObject(): RentalObject
    {
        return $this->object;
    }

    public function getUnityProvider(): UnityProvider
    {
        return $this->unityProvider;
    }

    public function getExpiryDate(): \DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function getContractNum(): ?string
    {
        return $this->contractNum;
    }

    public function getContractDate(): ?\DateTimeImmutable
    {
        return $this->contractDate;
    }

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setSuspended(bool $suspended): void
    {
        $this->isSuspended = $suspended;
    }

    public function getUnities(): Collection
    {
        return $this->unities;
    }

    public function configure(RentalObject $object, UnityProvider $provider, \DateTimeImmutable $expiry, ?string $number, ?\DateTimeImmutable $date, bool $suspended, array $unities): void
    {
        $this->object = $object;
        $this->unityProvider = $provider;
        $this->expiryDate = $expiry;
        $this->contractNum = $number;
        $this->contractDate = $date;
        $this->isSuspended = $suspended;
        $this->unityIdsSnapshot = array_map(static fn (Unity $unity): int => $unity->getId(), $unities);
        sort($this->unityIdsSnapshot);
        $this->unities->clear();
        foreach ($unities as $unity) {
            $this->unities->add($unity);
        }
    }
}
