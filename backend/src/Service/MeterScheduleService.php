<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MeterScheduleService
{
    public function __construct(private EntityManagerInterface $em, private NotificationTypeService $types)
    {
    }

    public function deadline(\DateTimeImmutable $month, int $day): \DateTimeImmutable
    {
        return $month->setTime(0, 0)->setDate((int) $month->format('Y'), (int) $month->format('n'), min($day, (int) $month->format('t')));
    }

    public function submission(Meter $meter, \DateTimeImmutable $month, int $day): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $date = $this->em->getConnection()->fetchOne('SELECT MIN(meter_reading_date) FROM meter_readings WHERE meter_id = ? AND meter_reading_date >= ? AND meter_reading_date < ?', [$meter->getId(), $start->format('Y-m-d'), $start->modify('+1 month')->format('Y-m-d')]);
        $submitted = false !== $date && null !== $date;

        return ['submitted' => $submitted, 'late' => $submitted && substr($date, 0, 10) > $this->deadline($month, $day)->format('Y-m-d')];
    }

    public function state(Meter $meter, ?array $installation, ?\DateTimeImmutable $today = null): array
    {
        if (null === $installation) {
            return ['effectiveReadingDay' => null, 'readingDueDate' => null, 'nextReadingDueDate' => null, 'readingRequired' => false, 'readingOverdue' => false, 'readingSubmitted' => false, 'readingSubmittedLate' => false, 'readingAttention' => false];
        }
        $today ??= new \DateTimeImmutable('today');
        $month = $today->modify('first day of this month');
        $day = $meter->getReadingDay() ?? $installation['object']['readingDay'];
        $due = $this->deadline($month, $day);
        $submission = $this->submission($meter, $month, $day);
        $submitted = $submission['submitted'];
        $type = $this->types->configuration(NotificationKind::MeterReading);

        return [
            'effectiveReadingDay' => $day, 'readingDueDate' => $due->format('Y-m-d'),
            'nextReadingDueDate' => $this->deadline($submitted ? $month->modify('+1 month') : $month, $day)->format('Y-m-d'),
            'readingAttention' => !$submitted && null !== $type && $type->isActive() && $meter->getCreateDate() <= $due && $today >= $this->types->notificationDate($type, $due),
            'readingRequired' => !$submitted, 'readingOverdue' => !$submitted && $today > $due, 'readingSubmitted' => $submitted, 'readingSubmittedLate' => $submission['late'],
        ];
    }
}
