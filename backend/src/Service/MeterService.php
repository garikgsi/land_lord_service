<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MeterInput;
use App\Dto\MeterInstallationInput;
use App\Dto\MeterListQuery;
use App\Entity\Meter;
use App\Entity\ObjectMeter;
use App\Entity\SpaceMeter;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MeterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UnityService $unities,
        private MeterInstallationService $installations,
        private MeterVerificationService $verifications,
        private MeterReadingService $readings,
        private MeterScheduleService $schedule,
    ) {
    }

    public function present(Meter $meter): array
    {
        $installation = $this->installations->present($meter);

        return [
            'id' => $meter->getId(), 'name' => $meter->getUtility()->getName().' · '.($meter->getSerialNumber() ?? '#'.$meter->getId()),
            'utility' => $this->unities->present($meter->getUtility()),
            'createDate' => $meter->getCreateDate()->format('Y-m-d'), 'startRead' => $meter->getStartRead(),
            'serialNumber' => $meter->getSerialNumber(), 'verificationIntervalDays' => $meter->getVerificationIntervalDays(),
            'verificationDate' => $meter->getVerificationDate()?->format('Y-m-d'), 'readingDay' => $meter->getReadingDay(),
            'installation' => $installation, 'lastReading' => $this->readings->latest($meter),
            ...$this->verifications->state($meter), ...$this->schedule->state($meter, $installation),
        ];
    }

    public function list(MeterListQuery $input): array
    {
        $query = $this->em->getRepository(Meter::class)->createQueryBuilder('m')
            ->join('m.utility', 'u')->addSelect('u')
            ->leftJoin(ObjectMeter::class, 'om', 'WITH', 'om.meter = m')->leftJoin('om.object', 'o')
            ->leftJoin(SpaceMeter::class, 'sm', 'WITH', 'sm.meter = m')->leftJoin('sm.space', 's')->leftJoin('s.object', 'so');

        if (null !== $input->objectId) {
            $query->andWhere('(o.id = :object AND o.isDeleted = false) OR (so.id = :object AND s.isDeleted = false AND so.isDeleted = false)')->setParameter('object', $input->objectId);
        }

        if (null !== $input->spaceId) {
            $query->andWhere('s.id = :space AND s.isDeleted = false')->setParameter('space', $input->spaceId);
        }

        if (null !== $input->utilityId) {
            $query->andWhere('u.id = :utility')->setParameter('utility', $input->utilityId);
        }

        if ($input->objectOnly) {
            $query->andWhere('om.id IS NOT NULL AND o.isDeleted = false');
        }

        if ('installed' === $input->state) {
            $query->andWhere('(om.id IS NOT NULL AND o.isDeleted = false) OR (sm.id IS NOT NULL AND s.isDeleted = false AND so.isDeleted = false)');
        } elseif ('uninstalled' === $input->state) {
            $query->andWhere('(om.id IS NULL OR o.isDeleted = true) AND (sm.id IS NULL OR s.isDeleted = true OR so.isDeleted = true)');
        }

        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(u.name) LIKE :query OR LOWER(m.serialNumber) LIKE :query OR LOWER(o.name) LIKE :query OR LOWER(s.name) LIKE :query OR LOWER(so.name) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }

        $total = (int) (clone $query)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('m.id', 'DESC')->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $items), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function save(MeterInput $input, ?Meter $meter = null): Meter
    {
        $utility = $this->unities->find($input->utilityId) ?? throw new InputException('utilityId', 'Коммуникация не найдена.');

        if ((null === $meter || $meter->getUtility()->getId() !== $utility->getId()) && !$utility->getUseMeter()) {
            throw new InputException('utilityId', 'Для этой коммуникации установка счётчиков не предусмотрена.');
        }

        if (null === $meter && (null === $input->objectId) === (null === $input->spaceId)) {
            throw new InputException('objectId', 'Выберите одно место установки: объект или помещение.');
        }

        return $this->em->wrapInTransaction(function () use ($input, $meter, $utility): Meter {
            $new = null === $meter;

            if (!$new) {
                $this->em->refresh($meter, LockMode::PESSIMISTIC_WRITE);
            }

            $date = new \DateTimeImmutable($input->createDate);
            $first = $new ? false : $this->em->getConnection()->fetchAssociative('SELECT MIN(meter_read) AS value, MIN(meter_reading_date) AS date FROM meter_readings WHERE meter_id = ?', [$meter->getId()]);

            if (false !== $first && null !== $first['date'] && ($input->startRead > (int) $first['value'] || $date->format('Y-m-d') > substr($first['date'], 0, 10))) {
                throw new InputException('startRead', 'Дата производства и начальное показание должны предшествовать внесённым показаниям.');
            }

            if (!$new && false !== ($firstVerification = $this->em->getConnection()->fetchOne('SELECT MIN(verification_date) FROM meter_verifications WHERE meter_id = ?', [$meter->getId()])) && null !== $firstVerification && $date->format('Y-m-d') > $firstVerification) {
                throw new InputException('createDate', 'Дата производства не может быть позже проведённых поверок.');
            }

            if (!$new && null !== ($provider = $this->installations->present($meter)['provider'] ?? null) && $provider['unity']['id'] !== $utility->getId()) {
                throw new InputException('utilityId', 'Сначала очистите привязку поставщика к счётчику перед сменой коммуникации.');
            }

            $meter ??= new Meter($utility, $date, (int) $input->startRead, (int) $input->verificationIntervalDays);
            $meter->setUtility($utility);
            $meter->setCreateDate($date);
            $meter->setStartRead((int) $input->startRead);
            $meter->setVerificationIntervalDays((int) $input->verificationIntervalDays);
            $meter->setSerialNumber(null === $input->serialNumber || '' === trim($input->serialNumber) ? null : trim($input->serialNumber));
            $meter->setReadingDay(null === $input->readingDay ? null : (int) $input->readingDay);
            $this->em->persist($meter);
            $this->em->flush();

            if ($new) {
                $this->installations->save($meter, new MeterInstallationInput($input->objectId, $input->spaceId, $input->comment, $input->objectUnityProviderId));
            }

            if (null !== $input->verificationDate && $input->verificationDate !== $meter->getVerificationDate()?->format('Y-m-d')) {
                $this->verifications->add($meter, new \DateTimeImmutable($input->verificationDate));
            }

            return $meter;
        });
    }

    public function remove(Meter $record): void
    {
        $this->em->wrapInTransaction(function () use ($record): void {
            $this->em->refresh($record, LockMode::PESSIMISTIC_WRITE);
            $record->markDeleted();
            $this->em->flush();
        });
    }
}
