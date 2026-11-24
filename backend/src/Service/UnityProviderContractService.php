<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UnityProviderContractInput;
use App\Dto\UnityProviderContractListQuery;
use App\Entity\Meter;
use App\Entity\ObjectMeter;
use App\Entity\RentalObject;
use App\Entity\Unity;
use App\Entity\UnityProvider;
use App\Entity\UnityProviderContract;
use App\Exception\InputException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UnityProviderContractService
{
    public function __construct(private EntityManagerInterface $em, private MeterService $meters)
    {
    }

    public function present(UnityProviderContract $contract): array
    {
        $query = $this->em->getRepository(Meter::class)->createQueryBuilder('m')
            ->join(ObjectMeter::class, 'om', 'WITH', 'om.meter = m')->join('om.objectUnityProvider', 'op')
            ->andWhere('op.object = :object AND op.unityProvider = :provider')
            ->setParameter('object', $contract->getObject())->setParameter('provider', $contract->getUnityProvider());
        $unities = $contract->getUnities()->toArray();
        if ([] !== $unities) {
            $query->andWhere('m.utility IN (:unities)')->setParameter('unities', $unities);
        }
        $status = $contract->getExpiryDate() < new \DateTimeImmutable('today') ? 'finished' : ($contract->isSuspended() ? 'suspended' : 'active');

        return [
            'id' => $contract->getId(), 'contractNum' => $contract->getContractNum(),
            'contractDate' => $contract->getContractDate()?->format('Y-m-d'), 'expiryDate' => $contract->getExpiryDate()->format('Y-m-d'),
            'isSuspended' => $contract->isSuspended(), 'status' => $status,
            'object' => ['id' => $contract->getObject()->getId(), 'name' => $contract->getObject()->getName()],
            'unityProvider' => ['id' => $contract->getUnityProvider()->getId(), 'name' => $contract->getUnityProvider()->getName()],
            'unities' => array_map(static fn (Unity $unity): array => ['id' => $unity->getId(), 'name' => $unity->getName()], $unities),
            'meters' => array_map($this->meters->present(...), $query->orderBy('m.id', 'DESC')->getQuery()->getResult()),
        ];
    }

    public function list(UnityProviderContractListQuery $input, ?int $objectId = null): array
    {
        $query = $this->em->getRepository(UnityProviderContract::class)->createQueryBuilder('c')
            ->join('c.object', 'o')->join('c.unityProvider', 'p')->addSelect('o', 'p');
        if (null !== $objectId) {
            $query->andWhere('o.id = :object')->setParameter('object', $objectId);
        }
        if (null !== $input->unityProviderId) {
            $query->andWhere('p.id = :provider')->setParameter('provider', $input->unityProviderId);
        }
        if (null !== $input->unityId) {
            $query->andWhere('c.unities IS EMPTY OR :unity MEMBER OF c.unities')->setParameter('unity', $input->unityId);
        }
        if ('all' !== $input->status) {
            $query->andWhere('finished' === $input->status ? 'c.expiryDate < :today' : 'c.expiryDate >= :today')
                ->setParameter('today', new \DateTimeImmutable('today'));
            if ('finished' !== $input->status) {
                $query->andWhere('c.isSuspended = :suspended')->setParameter('suspended', 'suspended' === $input->status);
            }
        }
        if ($input->withMeters) {
            $query->andWhere('EXISTS (SELECT om.id FROM '.ObjectMeter::class.' om JOIN om.meter m JOIN om.objectUnityProvider op WHERE op.object = c.object AND op.unityProvider = c.unityProvider AND (c.unities IS EMPTY OR m.utility MEMBER OF c.unities)'.(null !== $input->unityId ? ' AND m.utility = :unity' : '').')');
        }
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(o.name) LIKE :query OR LOWER(p.name) LIKE :query OR LOWER(c.contractNum) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $total = (int) (clone $query)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('o.name', 'ASC')->addOrderBy('o.id', 'ASC')->addOrderBy('c.expiryDate', 'ASC')->addOrderBy('c.id', 'DESC')
            ->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function save(UnityProviderContractInput $input, ?UnityProviderContract $contract = null): UnityProviderContract
    {
        $object = $this->em->getRepository(RentalObject::class)->find($input->objectId) ?? throw new InputException('objectId', 'Объект не найден.');
        $provider = $this->em->getRepository(UnityProvider::class)->find($input->unityProviderId) ?? throw new InputException('unityProviderId', 'Поставщик не найден.');
        $unities = [];
        foreach (array_unique($input->unityIds) as $id) {
            $unities[] = $this->em->getRepository(Unity::class)->find($id) ?? throw new InputException('unityIds', 'Коммуникация не найдена.');
        }

        return $this->em->wrapInTransaction(function () use ($input, $contract, $object, $provider, $unities): UnityProviderContract {
            $record = $contract ?? new UnityProviderContract($object, $provider, new \DateTimeImmutable($input->expiryDate));
            $record->configure($object, $provider, new \DateTimeImmutable($input->expiryDate), null === $input->contractNum ? null : (trim($input->contractNum) ?: null), null === $input->contractDate || '' === trim($input->contractDate) ? null : new \DateTimeImmutable($input->contractDate), $input->isSuspended, $unities);
            $this->em->persist($record);
            $this->em->flush();

            return $record;
        });
    }

    public function remove(UnityProviderContract $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
