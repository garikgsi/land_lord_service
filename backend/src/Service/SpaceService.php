<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SpaceInput;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\Space;
use App\Exception\InputException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SpaceService
{
    public function __construct(private EntityManagerInterface $em, private ContractInterventionService $interventions)
    {
    }

    public function create(RentalObject $object, Renter $renter, string $square, string $name): Space
    {
        $space = new Space($object, '' === trim($name) ? $square.' кв.м '.$renter->getName() : trim($name), $square);
        $this->em->persist($space);

        return $space;
    }

    public function reviseForDraft(Space $space, int $contractId, RentalObject $object, string $square, Renter $oldRenter, Renter $renter): Space
    {
        $name = $space->getName() === $space->getSquare().' кв.м '.$oldRenter->getName()
            ? $square.' кв.м '.$renter->getName() : $space->getName();
        $shared = (bool) $this->em->getConnection()->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM contracts WHERE NOT is_deleted AND space_id = ? AND id <> ?)',
            [$space->getId(), $contractId],
        );
        if ($shared) {
            return $this->create($object, $renter, $square, $name);
        }
        $space->setObject($object);
        $space->setSquare($square);
        $space->setName($name);

        return $space;
    }

    public function area(RentalObject $object, ?int $exceptContractId = null): array
    {
        $area = $this->em->getConnection()->fetchAssociative(
            'SELECT COALESCE(SUM(s.square), 0)::text AS occupied, (?::numeric - COALESCE(SUM(s.square), 0))::text AS available FROM spaces s WHERE NOT s.is_deleted AND s.object_id = ? AND EXISTS (SELECT 1 FROM contracts c WHERE NOT c.is_deleted AND c.space_id = s.id AND (?::int IS NULL OR c.id <> ?::int) AND LEAST(c.expiry_date, c.annulment_date) >= CURRENT_DATE AND LEAST(c.expiry_date, c.annulment_date) >= c.contract_date)',
            [$object->getSquare(), $object->getId(), $exceptContractId, $exceptContractId],
        );

        return ['today' => (new \DateTimeImmutable('today'))->format('Y-m-d'), 'square' => $object->getSquare(), 'occupiedSquare' => $area['occupied'], 'availableSquare' => $area['available']];
    }

    public function assertAvailable(RentalObject $object): void
    {
        if (str_starts_with($this->area($object)['availableSquare'], '-')) {
            throw new InputException('square', 'Недостаточно свободной площади на объекте. Обновите данные и уменьшите площадь.');
        }
    }

    public function present(Space $space): array
    {
        $contract = $this->em->getConnection()->fetchAssociative(
            'SELECT id, LEAST(expiry_date, annulment_date) AS end_date FROM contracts WHERE NOT is_deleted AND space_id = ? ORDER BY CASE WHEN contract_date <= CURRENT_DATE AND LEAST(expiry_date, annulment_date) >= CURRENT_DATE THEN 0 WHEN LEAST(expiry_date, annulment_date) >= CURRENT_DATE THEN 1 ELSE 2 END, contract_date DESC, id DESC LIMIT 1',
            [$space->getId()],
        );

        return [
            'contractEndDate' => false === $contract ? null : $contract['end_date'],
            'contractAttention' => false !== $contract && $this->interventions->requiresAttention((int) $contract['id']),
            'id' => $space->getId(), 'name' => $space->getName(), 'square' => $space->getSquare(),
            'object' => ['id' => $space->getObject()->getId(), 'name' => $space->getObject()->getName()],
            'active' => $this->active($space),
        ];
    }

    public function active(Space $space): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM contracts WHERE NOT is_deleted AND space_id = ? AND LEAST(expiry_date, annulment_date) >= CURRENT_DATE AND LEAST(expiry_date, annulment_date) >= contract_date)',
            [$space->getId()],
        );
    }

    public function forObject(RentalObject $object): array
    {
        $records = $this->em->getRepository(Space::class)->findBy(['object' => $object]);
        $items = array_map($this->present(...), $records);
        usort($items, static fn (array $left, array $right): int => ($right['active'] <=> $left['active']) ?: ($right['id'] <=> $left['id']));

        return [
            'items' => $items, 'total' => count($records),
            'page' => 1, 'pageSize' => count($records),
            ...$this->area($object),
        ];
    }

    public function rename(Space $space, SpaceInput $input): Space
    {
        $space->setName(trim($input->name));
        $this->em->flush();

        return $space;
    }

    public function removeIfUnused(Space $space): void
    {
        if (!(bool) $this->em->getConnection()->fetchOne('SELECT EXISTS(SELECT 1 FROM contracts WHERE NOT is_deleted AND space_id = ?)', [$space->getId()])) {
            $space->markDeleted();
        }
    }
}
