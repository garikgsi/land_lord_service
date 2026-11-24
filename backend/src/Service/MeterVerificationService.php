<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use App\Entity\MeterVerification;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MeterVerificationService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function state(Meter $meter, ?\DateTimeImmutable $today = null): array
    {
        $base = $meter->getVerificationDate() ?? $meter->getCreateDate();
        $due = $base->modify('+'.$meter->getVerificationIntervalDays().' days');

        return ['nextVerificationDate' => $due->format('Y-m-d'), 'verificationExpired' => $due < ($today ?? new \DateTimeImmutable('today'))];
    }

    public function list(Meter $meter): array
    {
        $items = array_map($this->present(...), $this->em->getRepository(MeterVerification::class)->findBy(['meter' => $meter], ['verificationDate' => 'DESC', 'id' => 'DESC']));

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function present(MeterVerification $record): array
    {
        return ['id' => $record->getId(), 'meterId' => $record->getMeter()->getId(), 'verificationDate' => $record->getVerificationDate()->format('Y-m-d')];
    }

    public function add(Meter $meter, \DateTimeImmutable $date): MeterVerification
    {
        return $this->em->wrapInTransaction(function () use ($meter, $date): MeterVerification {
            $this->em->refresh($meter, LockMode::PESSIMISTIC_WRITE);
            if ($date < $meter->getCreateDate()) {
                throw new InputException('verificationDate', 'Поверка не может быть раньше даты производства.');
            }
            if ($date > new \DateTimeImmutable('today')) {
                throw new InputException('verificationDate', 'Дата поверки не может быть в будущем.');
            }

            $record = $this->em->getRepository(MeterVerification::class)->findOneBy(['meter' => $meter, 'verificationDate' => $date])
                ?? new MeterVerification($meter, $date);
            if (null === $meter->getVerificationDate() || $date > $meter->getVerificationDate()) {
                $meter->setVerificationDate($date);
            }
            $this->em->persist($record);
            $this->em->flush();

            return $record;
        });
    }
}
