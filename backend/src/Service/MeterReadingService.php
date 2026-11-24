<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MeterReadingInput;
use App\Dto\RegistryListQuery;
use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MeterReadingService
{
    public function __construct(private EntityManagerInterface $em, private MeterScheduleService $schedule, private MeterInstallationService $installations)
    {
    }

    public function latest(Meter $meter): ?array
    {
        $record = $this->em->getRepository(MeterReading::class)->findOneBy(['meter' => $meter], ['meterReadingDate' => 'DESC', 'id' => 'DESC']);

        return null === $record ? null : $this->present($record);
    }

    public function present(MeterReading $record): array
    {
        return ['id' => $record->getId(), 'meterId' => $record->getMeter()->getId(), 'meterReadingDate' => $record->getMeterReadingDate()->format('Y-m-d\TH:i:s'), 'meterRead' => $record->getMeterRead()];
    }

    public function list(Meter $meter, RegistryListQuery $input): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'WITH timeline AS (SELECT id, meter_id, meter_reading_date, meter_read, LAG(meter_read) OVER (ORDER BY meter_reading_date, id) AS previous_read, LAG(meter_reading_date) OVER (ORDER BY meter_reading_date, id) AS previous_date FROM meter_readings WHERE meter_id = ?) SELECT *, meter_read - COALESCE(previous_read, ?) AS consumption, COALESCE(previous_date, ?::timestamp) AS interval_from FROM timeline ORDER BY meter_reading_date DESC, id DESC LIMIT 20 OFFSET ?',
            [$meter->getId(), $meter->getStartRead(), $meter->getCreateDate()->format('Y-m-d'), ($input->page - 1) * 20],
        );
        $day = $meter->getReadingDay() ?? ($this->installations->present($meter)['object']['readingDay'] ?? null);
        $items = array_map(function (array $row) use ($meter, $day): array {
            $date = new \DateTimeImmutable($row['meter_reading_date']);
            $due = null === $day ? null : $this->schedule->deadline($date, $day)->format('Y-m-d');
            $timestamp = $date->format('Y-m-d\TH:i:s');

            return [
                'id' => (int) $row['id'], 'meterId' => (int) $row['meter_id'],
                'meterReadingDate' => $timestamp,
                'previousRead' => (int) ($row['previous_read'] ?? $meter->getStartRead()),
                'meterRead' => (int) $row['meter_read'], 'consumption' => (int) $row['consumption'],
                'intervalFrom' => (new \DateTimeImmutable($row['interval_from']))->format('Y-m-d\TH:i:s'),
                'intervalTo' => $timestamp,
                'readingDueDate' => $due,
                'submissionStatus' => null === $due ? null : ($date->format('Y-m-d') <= $due ? 'on_time' : 'late'),
            ];
        }, $rows);

        return ['items' => $items, 'total' => $this->em->getRepository(MeterReading::class)->count(['meter' => $meter]), 'page' => $input->page, 'pageSize' => 20];
    }

    public function previous(Meter $meter, \DateTimeImmutable $date): array
    {
        if ($date->format('Y-m-d') < $meter->getCreateDate()->format('Y-m-d')) {
            throw new InputException('date', 'Дата не может быть раньше производства.');
        }
        $previous = $this->em->getConnection()->fetchAssociative('SELECT meter_read, meter_reading_date FROM meter_readings WHERE meter_id = ? AND meter_reading_date < ? ORDER BY meter_reading_date DESC, id DESC LIMIT 1', [$meter->getId(), $date->format('Y-m-d H:i:s')]);

        $monthStart = $date->modify('first day of this month')->setTime(0, 0);
        $monthReading = $this->em->getConnection()->fetchOne('SELECT meter_reading_date FROM meter_readings WHERE meter_id = ? AND meter_reading_date >= ? AND meter_reading_date < ? ORDER BY meter_reading_date DESC, id DESC LIMIT 1', [$meter->getId(), $monthStart->format('Y-m-d H:i:s'), $monthStart->modify('+1 month')->format('Y-m-d H:i:s')]);

        return [
            'monthReadingDate' => false === $monthReading ? null : (new \DateTimeImmutable($monthReading))->format('Y-m-d'),
            'meterRead' => false === $previous ? $meter->getStartRead() : (int) $previous['meter_read'],
            'meterReadingDate' => false === $previous ? $meter->getCreateDate()->format('Y-m-d\TH:i:s') : (new \DateTimeImmutable($previous['meter_reading_date']))->format('Y-m-d\TH:i:s'),
        ];
    }

    public function save(Meter $meter, MeterReadingInput $input, ?MeterReading $record = null): MeterReading
    {
        if (null !== $record && $record->getMeter()->getId() !== $meter->getId()) {
            throw new NotFoundHttpException('Показание не найдено у этого прибора.');
        }

        return $this->em->wrapInTransaction(function () use ($meter, $input, $record): MeterReading {
            $this->em->refresh($meter, LockMode::PESSIMISTIC_WRITE);
            $date = new \DateTimeImmutable($input->meterReadingDate);
            if ($date->format('Y-m-d') < $meter->getCreateDate()->format('Y-m-d')) {
                throw new InputException('meterReadingDate', 'Показание не может быть раньше даты производства.');
            }
            $id = $record?->getId() ?? 0;
            $duplicate = $this->em->getConnection()->fetchOne('SELECT id FROM meter_readings WHERE meter_id = ? AND meter_reading_date = ? AND id <> ?', [$meter->getId(), $date->format('Y-m-d H:i:s'), $id]);
            if (false !== $duplicate) {
                throw new InputException('meterReadingDate', 'За этот момент уже есть показание. Исправьте существующую запись.');
            }
            $previous = $this->em->getConnection()->fetchOne('SELECT meter_read FROM meter_readings WHERE meter_id = ? AND meter_reading_date < ? AND id <> ? ORDER BY meter_reading_date DESC LIMIT 1', [$meter->getId(), $date->format('Y-m-d H:i:s'), $id]);
            $following = $this->em->getConnection()->fetchOne('SELECT meter_read FROM meter_readings WHERE meter_id = ? AND meter_reading_date > ? AND id <> ? ORDER BY meter_reading_date LIMIT 1', [$meter->getId(), $date->format('Y-m-d H:i:s'), $id]);
            if ($input->meterRead < max($meter->getStartRead(), false === $previous ? 0 : (int) $previous) || (false !== $following && $input->meterRead > (int) $following)) {
                throw new InputException('meterRead', 'Показание должно быть не меньше предыдущего и не больше следующего.');
            }
            $record ??= new MeterReading($meter, $date, (int) $input->meterRead);
            $record->setMeterReadingDate($date);
            $record->setMeterRead((int) $input->meterRead);
            $this->em->persist($record);
            $this->em->flush();

            return $record;
        });
    }
}
