<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContractAmendmentInput;
use App\Dto\ContractIndexationInput;
use App\Dto\ContractInput;
use App\Dto\ContractListQuery;
use App\Dto\RegistryListQuery;
use App\Entity\Contract;
use App\Entity\RentalObject;
use App\Entity\Space;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ContractService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SpaceService $spaces,
        private RenterService $renters,
        private ContractTypeService $types,
        private LandLordService $landLords,
        private RentIndexationCalculator $numbers,
        private ContractAccrualService $accruals,
        private ContractInterventionService $interventions,
        #[Autowire('%kernel.secret%')]
        private string $signingSecret,
    ) {
    }

    public function hasUnfinishedForObject(RentalObject $object): bool
    {
        return (int) $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')->join('c.space', 's')
            ->andWhere('s.object = :object AND c.expiryDate >= :today')
            ->andWhere('c.annulmentDate IS NULL OR c.annulmentDate >= :today')
            ->andWhere('c.annulmentDate IS NULL OR c.annulmentDate >= c.contractDate')
            ->setParameter('object', $object)->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult() > 0;
    }

    public function effectiveEnd(Contract $record): \DateTimeImmutable
    {
        return null !== $record->getAnnulmentDate() && $record->getAnnulmentDate() < $record->getExpiryDate()
            ? $record->getAnnulmentDate() : $record->getExpiryDate();
    }

    public function canRecontract(Contract $record): bool
    {
        if ($record->isDeleted() || $this->effectiveEnd($record) < new \DateTimeImmutable('today') || $this->effectiveEnd($record) < $record->getContractDate()) {
            return false;
        }

        return !(bool) $this->em->getConnection()->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM contracts WHERE NOT is_deleted AND space_id = ? AND id <> ? AND contract_date >= ? AND LEAST(expiry_date, annulment_date) >= contract_date)',
            [$record->getSpace()->getId(), $record->getId(), $record->getContractDate()->format('Y-m-d')],
        );
    }

    public function present(Contract $record): array
    {
        $end = $this->effectiveEnd($record);
        $today = new \DateTimeImmutable('today');
        $status = $end < $record->getContractDate() ? 'cancelled' : ($end < $today ? 'finished' : ($record->getContractDate() > $today ? 'upcoming' : 'active'));

        return [
            'validFrom' => $record->getValidFrom()?->format('Y-m-d\TH:i:s.u\Z'),
            'isSigned' => $record->isSigned(),
            'id' => $record->getId(), 'contractNum' => $record->getContractNum(),
            'contractDate' => $record->getContractDate()->format('Y-m-d'), 'expiryDate' => $record->getExpiryDate()->format('Y-m-d'),
            'annulmentDate' => $record->getAnnulmentDate()?->format('Y-m-d'), 'effectiveEnd' => $end->format('Y-m-d'),
            'monthlyPayment' => $record->getMonthlyPayment(), 'securityPayment' => $record->getSecurityPayment(),
            'lastIndexationDate' => $record->getLastIndexationDate()?->format('Y-m-d'),
            'increaseDate' => $record->getIncreaseDate()?->format('Y-m-d'), 'increasePercent' => $record->getIncreasePercent(),
            'increaseIntervalMonths' => $record->getIncreaseIntervalMonths(),
            'nextIndexationReminderDate' => $this->reminderDate($record, new \DateTimeImmutable('today'))?->format('Y-m-d'),
            'space' => $this->spaces->present($record->getSpace()),
            'renter' => $this->renters->present($record->getRenter()),
            'landLord' => $this->landLords->present($record->getLandLord()),
            'contractType' => $this->types->present($record->getContractType()),
            'status' => $status, 'canRecontract' => $this->canRecontract($record),
            'attentionDates' => $this->interventions->attentionDates($record->getId()),
        ];
    }

    public function list(ContractListQuery $input): array
    {
        $query = $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->addSelect('s', 'o', 'r', 't', 'l', 'w')->join('c.space', 's')->join('s.object', 'o')
            ->join('c.renter', 'r')->join('r.ownership', 'w')->join('c.contractType', 't')->join('c.landLord', 'l');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(c.contractNum) LIKE :query OR LOWER(r.name) LIKE :query OR LOWER(s.name) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        foreach (['objectId' => 'o.id', 'renterId' => 'r.id', 'spaceId' => 's.id'] as $field => $column) {
            if (null !== $input->$field) {
                $query->andWhere($column.' = :'.$field)->setParameter($field, $input->$field);
            }
        }
        $end = '(CASE WHEN c.annulmentDate IS NOT NULL AND c.annulmentDate < c.expiryDate THEN c.annulmentDate ELSE c.expiryDate END)';
        if (null !== $input->expiryFrom || null !== $input->expiryTo) {
            $query->andWhere($end.' >= c.contractDate');
        }
        if (null !== $input->expiryFrom) {
            $query->andWhere($end.' >= :from')->setParameter('from', $input->expiryFrom);
        }
        if (null !== $input->expiryTo) {
            $query->andWhere('c.contractDate <= :to')->setParameter('to', $input->expiryTo);
        }
        if ('all' !== $input->status) {
            $condition = match ($input->status) {
                'cancelled' => $end.' < c.contractDate',
                'finished' => $end.' >= c.contractDate AND '.$end.' < :today',
                'upcoming' => $end.' >= c.contractDate AND '.$end.' >= :today AND c.contractDate > :today',
                'active' => $end.' >= :today AND c.contractDate <= :today',
            };
            $query->andWhere($condition);
            if ('cancelled' !== $input->status) {
                $query->setParameter('today', new \DateTimeImmutable('today'));
            }
        }
        $total = (int) (clone $query)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('c.expiryDate', 'ASC')->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function forObject(RentalObject $object, RegistryListQuery $input): array
    {
        $page = $this->interventions->contractPageForObject($object->getId(), $input->page);
        $items = [];
        if ([] !== $page['ids']) {
            $records = $this->em->getRepository(Contract::class)->createQueryBuilder('c')
                ->addSelect('s', 'o', 'r', 't', 'l', 'w')->join('c.space', 's')->join('s.object', 'o')
                ->join('c.renter', 'r')->join('r.ownership', 'w')->join('c.contractType', 't')->join('c.landLord', 'l')
                ->andWhere('c.id IN (:ids)')->setParameter('ids', $page['ids'])->getQuery()->getResult();
            foreach ($records as $record) {
                $items[$record->getId()] = $this->present($record);
            }
        }

        return ['items' => array_map(static fn (int $id): array => $items[$id], $page['ids']), 'total' => $page['total'], 'page' => $input->page, 'pageSize' => 20];
    }

    public function legalList(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->addSelect('s', 'o', 'r', 't', 'l', 'w')->join('c.space', 's')->join('s.object', 'o')
            ->join('c.renter', 'r')->join('r.ownership', 'w')->join('c.contractType', 't')->join('c.landLord', 'l')
            ->andWhere('c.contractDate <= :today AND c.expiryDate >= :today AND (c.annulmentDate IS NULL OR c.annulmentDate >= :today)')
            ->setParameter('today', new \DateTimeImmutable('today'));
        $total = (int) (clone $query)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('o.name', 'ASC')->addOrderBy('o.id', 'ASC')->addOrderBy('r.name', 'ASC')->addOrderBy('c.id', 'DESC')
            ->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function signingPreview(ContractInput $input, ?Contract $record = null): array
    {
        if (!$input->isSigned || $record?->isSigned()) {
            throw new InputException('isSigned', 'Расчёт доступен только при подписании неподписанного договора.');
        }
        $end = new \DateTimeImmutable($input->expiryDate);
        if (null !== $record?->getAnnulmentDate()) {
            $end = min($end, $record->getAnnulmentDate());
        }
        $plan = $this->accruals->plan(new \DateTimeImmutable($input->contractDate), $end, $input->monthlyPayment);
        $terms = (array) $input;
        unset($terms['signingConfirmation']);
        $plan['confirmation'] = hash_hmac('sha256', json_encode([
            'contractId' => $record?->getId(),
            'validFrom' => $record?->getValidFrom()?->format('Y-m-d H:i:s.u'),
            'terms' => $terms,
            'plan' => $plan,
        ], JSON_THROW_ON_ERROR), $this->signingSecret);

        return $plan;
    }

    public function save(ContractInput $input, ?Contract $record = null): Contract
    {
        $object = $this->em->getRepository(RentalObject::class)->find($input->objectId) ?? throw new InputException('objectId', 'Объект не найден.');
        $renter = $this->renters->find($input->renterId) ?? throw new InputException('renterId', 'Арендатор не найден.');
        $type = $this->types->find($input->contractTypeId) ?? throw new InputException('contractTypeId', 'Тип договора не найден.');
        $previous = null === $input->previousContractId ? null : $this->em->getRepository(Contract::class)->find($input->previousContractId);
        if (null !== $input->previousContractId && null === $previous) {
            throw new InputException('previousContractId', 'Предыдущий договор не найден.');
        }
        if (null === $record && null === $previous && (null === $input->square || '' === $input->square)) {
            throw new InputException('square', 'Укажите площадь нового помещения.');
        }
        $start = new \DateTimeImmutable($input->contractDate);
        $end = new \DateTimeImmutable($input->expiryDate);

        return $this->em->wrapInTransaction(function () use ($input, $record, $previous, $object, $renter, $type, $start, $end): Contract {
            $originalSpace = $record?->getSpace() ?? $previous?->getSpace();
            $sourceObject = $originalSpace?->getObject();
            $objects = [$object->getId() => $object];
            if (null !== $sourceObject) {
                $objects[$sourceObject->getId()] = $sourceObject;
            }
            ksort($objects);
            foreach ($objects as $lockedObject) {
                $this->em->refresh($lockedObject, LockMode::PESSIMISTIC_WRITE);
            }
            if (null !== $originalSpace) {
                $this->em->refresh($originalSpace, LockMode::PESSIMISTIC_WRITE);
                if ($originalSpace->getObject()->getId() !== $sourceObject->getId()) {
                    throw new ConflictHttpException('Помещение было перемещено другим пользователем. Обновите договор.');
                }
            }
            if (null !== $previous) {
                $this->em->refresh($previous, LockMode::PESSIMISTIC_WRITE);
                if ($previous->getSpace()->getId() !== $originalSpace->getId()) {
                    throw new ConflictHttpException('Помещение предыдущего договора изменилось. Обновите данные.');
                }
            }
            if (null !== $record) {
                $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
                if ($record->getSpace()->getId() !== $originalSpace->getId()) {
                    throw new ConflictHttpException('Помещение договора изменилось. Обновите договор.');
                }
                if ($record->isSigned() && !$input->isSigned) {
                    throw new ConflictHttpException('Подписание договора нельзя отменить.');
                }
            }
            $space = $record?->getSpace() ?? $previous?->getSpace();
            $wasSigned = $record?->isSigned() ?? false;
            if ($wasSigned && null !== $input->signingConfirmation) {
                throw new ConflictHttpException('Договор уже подписан. Обновите карточку перед изменением реквизитов.');
            }
            $signingPlan = $input->isSigned && !$wasSigned ? $this->signingPreview($input, $record) : null;
            if (null !== $signingPlan && $signingPlan['monthCount'] > 0 && !hash_equals($signingPlan['confirmation'], $input->signingConfirmation ?? '')) {
                throw new InputException('signingConfirmation', 'Расчёт требует подтверждения. Проверьте сумму и подтвердите начисление.');
            }
            if (null !== $space) {
                $objectChanged = $space->getObject()->getId() !== $object->getId();
                $squareChanged = null !== $input->square && (bool) $this->em->getConnection()->fetchOne('SELECT ?::numeric <> ?::numeric', [$input->square, $space->getSquare()]);
                if ($wasSigned && $objectChanged) {
                    throw new InputException('objectId', 'Объект подписанного договора не меняется. Оформите новый договор.');
                }
                if ($squareChanged && (null === $record || $wasSigned)) {
                    throw new InputException('square', 'Площадь подписанного или повторно используемого Space не меняется. Создайте новое помещение.');
                }
                if (null !== $record && !$wasSigned && ($objectChanged || $squareChanged)) {
                    $this->validatePeriods($space, null, null, null, $record->getId());
                    $space = $this->spaces->reviseForDraft($space, $record->getId(), $object, $input->square ?? $space->getSquare(), $record->getRenter(), $renter);
                }
            }
            if (null === $record && null !== $previous) {
                if ($previous->getSpace()->getObject()->getId() !== $object->getId() || !$this->canRecontract($previous)) {
                    throw new InputException('previousContractId', 'Этот договор нельзя перезаключить на прежнем Space. После освобождения создайте новое помещение.');
                }
                if ($start != $this->effectiveEnd($previous)->modify('+1 day')) {
                    throw new InputException('contractDate', 'Новый договор должен начинаться на следующий день после окончания предыдущего, без перерыва.');
                }
            }
            if (null === $space) {
                $space = $this->spaces->create($object, $renter, $input->square, $input->spaceName);
            } elseif (null !== $space->getId()) {
                $this->validatePeriods($space, $start, $end, $record?->getAnnulmentDate(), $record?->getId());
            }
            $saved = $record ?? new Contract($space, $renter, $object->getLandLord(), $type, $input->securityPayment, $end, $start, trim($input->contractNum), $input->monthlyPayment);
            $saved->setSpace($space);
            if (!$wasSigned) {
                $saved->setLandLord($object->getLandLord());
            }
            $saved->setSigned($input->isSigned);
            $saved->setRenter($renter);
            $saved->setContractType($type);
            $saved->setContractNum(trim($input->contractNum));
            $saved->setContractDate($start);
            $saved->setExpiryDate($end);
            $saved->setMonthlyPayment($input->monthlyPayment);
            $saved->setSecurityPayment($input->securityPayment);
            $saved->setIncreaseDate(null === $input->increaseDate ? null : new \DateTimeImmutable($input->increaseDate));
            $saved->setIncreasePercent($input->increasePercent);
            if (null === $saved->getIncreaseDate()) {
                $saved->setIncreaseIntervalMonths(null);
            }
            $this->em->persist($saved);
            $this->em->flush();
            $this->spaces->assertAvailable($object);
            if (null !== $signingPlan) {
                $this->accruals->postSigning($saved, $signingPlan['periods']);
            }

            return $saved;
        });
    }

    public function remove(Contract $record): void
    {
        $this->withLockedContract($record, function () use ($record): Contract {
            if ($record->isSigned()) {
                throw new ConflictHttpException('Подписанный договор удалять нельзя.');
            }
            $record->markDeleted();
            $this->em->flush();
            $this->spaces->removeIfUnused($record->getSpace());
            $this->em->flush();

            return $record;
        });
    }

    public function amend(Contract $record, ContractAmendmentInput $input): Contract
    {
        $renter = $this->renters->find($input->renterId) ?? throw new InputException('renterId', 'Арендатор не найден.');

        return $this->withLockedContract($record, function () use ($record, $input, $renter): Contract {
            $end = new \DateTimeImmutable($input->expiryDate);
            if ($end < $record->getContractDate()) {
                throw new InputException('expiryDate', 'Окончание не может быть раньше начала аренды.');
            }
            $this->validatePeriods($record->getSpace(), $record->getContractDate(), $end, $record->getAnnulmentDate(), $record->getId());
            $record->setRenter($renter);
            $record->setExpiryDate($end);
            $record->setMonthlyPayment($input->monthlyPayment);
            $this->em->flush();

            return $record;
        });
    }

    public function index(Contract $record, ContractIndexationInput $input): Contract
    {
        return $this->withLockedContract($record, function () use ($record, $input): Contract {
            $date = new \DateTimeImmutable($input->increaseDate);
            if ($date < $record->getContractDate() || $date > $this->effectiveEnd($record)) {
                throw new InputException('increaseDate', 'Дата индексации должна входить в период аренды.');
            }
            $current = $record->getMonthlyPayment();
            if (!$this->numbers->same($current, $input->currentMonthlyPayment)) {
                throw new ConflictHttpException('Ежемесячный платёж изменился. Откройте индексацию заново.');
            }
            $amount = 'percent' === $input->calculationMode
                ? $this->numbers->amount($current, $input->increasePercent)
                : bcround($input->newMonthlyPayment, 2);
            $percent = 'percent' === $input->calculationMode
                ? $input->increasePercent : $this->numbers->percent($current, $amount);
            $record->setIncreaseDate($date);
            $record->setLastIndexationDate($date);
            $record->setIncreasePercent($percent);
            $record->setIncreaseIntervalMonths($input->periodic ? $input->intervalMonths : null);
            $record->setMonthlyPayment($amount);
            $this->em->flush();

            return $record;
        });
    }

    public function annul(Contract $record, \DateTimeImmutable $date): Contract
    {
        return $this->withLockedContract($record, function () use ($record, $date): Contract {
            $this->validatePeriods($record->getSpace(), $record->getContractDate(), $record->getExpiryDate(), $date, $record->getId());
            $record->setAnnulmentDate($date);
            $this->em->flush();

            return $record;
        });
    }

    public function calendar(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $records = $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->andWhere('c.contractDate < :end AND c.expiryDate >= :start')
            ->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getResult();
        $events = [];
        foreach ($records as $record) {
            $effectiveEnd = $this->effectiveEnd($record);
            if ($effectiveEnd < $record->getContractDate()) {
                continue;
            }
            $url = '/contracts/'.$record->getId();
            if ($effectiveEnd >= $start && $effectiveEnd < $end) {
                $events[] = ['id' => 'end-'.$record->getId(), 'title' => 'Окончание договора № '.$record->getContractNum(), 'start' => $effectiveEnd->format('Y-m-d'), 'allDay' => true, 'url' => $url, 'color' => '#d63939'];
            }
            $reminder = $this->reminderDate($record, $start);
            while (null !== $reminder && $reminder < $end) {
                $events[] = ['id' => 'reminder-'.$record->getId().'-'.$reminder->format('Ymd'), 'title' => 'Напоминание об индексации № '.$record->getContractNum(), 'start' => $reminder->format('Y-m-d'), 'allDay' => true, 'url' => $url, 'color' => '#f76707'];
                $reminder = $this->reminderDate($record, $reminder->modify('+1 day'));
            }
        }

        return $events;
    }

    public function reminderDate(Contract $record, \DateTimeImmutable $from): ?\DateTimeImmutable
    {
        $anchor = $record->getIncreaseDate();
        $months = $record->getIncreaseIntervalMonths();
        if (null === $anchor || null === $months) {
            return null;
        }
        $distance = ((int) $from->format('Y') - (int) $anchor->format('Y')) * 12 + (int) $from->format('n') - (int) $anchor->format('n');
        $occurrence = max(1, intdiv(max(0, $distance), $months));
        do {
            $first = $anchor->modify('first day of this month')->modify('+'.($months * $occurrence).' months');
            $day = min((int) $anchor->format('j'), (int) $first->format('t'));
            $date = $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
            ++$occurrence;
        } while ($date < $from);

        return $date <= $this->effectiveEnd($record) && $date >= $record->getContractDate() ? $date : null;
    }

    private function withLockedContract(Contract $record, callable $action): Contract
    {
        return $this->em->wrapInTransaction(function () use ($record, $action): Contract {
            $space = $record->getSpace();
            $object = $space->getObject();
            $this->em->refresh($object, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($space, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            if ($record->getSpace()->getId() !== $space->getId() || $space->getObject()->getId() !== $object->getId()) {
                throw new ConflictHttpException('Помещение договора изменилось. Обновите договор.');
            }
            $result = $action();
            $this->spaces->assertAvailable($object);

            return $result;
        });
    }

    private function validatePeriods(Space $space, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, ?\DateTimeImmutable $annulment, ?int $exceptId): void
    {
        $periods = [];
        foreach ($this->em->getRepository(Contract::class)->findBy(['space' => $space]) as $other) {
            if ($other->getId() === $exceptId || $this->effectiveEnd($other) < $other->getContractDate()) {
                continue;
            }
            $periods[] = [$other->getContractDate(), $this->effectiveEnd($other)];
        }
        $effective = null !== $annulment && $annulment < $end ? $annulment : $end;
        if (null !== $start && null !== $effective && $effective >= $start) {
            $periods[] = [$start, $effective];
        }
        usort($periods, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        for ($index = 1; $index < count($periods); ++$index) {
            if ($periods[$index][0] <= $periods[$index - 1][1]) {
                throw new ConflictHttpException('Периоды договоров одного помещения пересекаются. Дата окончания включена в аренду.');
            }
            if ($periods[$index][0] != $periods[$index - 1][1]->modify('+1 day')) {
                throw new ConflictHttpException('Изменение создаёт перерыв между договорами одного Space. После освобождения нужен новый Space.');
            }
        }
    }
}
