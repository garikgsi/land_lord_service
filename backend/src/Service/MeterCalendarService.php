<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MeterCalendarService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MeterInstallationService $installations,
        private MeterScheduleService $schedule,
        private MeterVerificationService $verifications,
    ) {
    }

    public function list(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $events = [];

        foreach ($this->em->getRepository(Meter::class)->findAll() as $meter) {
            $place = $this->installations->present($meter);

            if (null === $place) {
                continue;
            }

            $label = $meter->getUtility()->getName().' · '.($meter->getSerialNumber() ?? '#'.$meter->getId()).' · '.$place['name'];
            $url = '/meters/'.$meter->getId();

            for ($month = $start->modify('first day of this month'); $month < $end; $month = $month->modify('+1 month')) {
                $due = $this->schedule->deadline($month, $meter->getReadingDay() ?? $place['object']['readingDay']);

                if ($due < $start || $due >= $end || $due < $meter->getCreateDate()) {
                    continue;
                }

                $submission = $this->schedule->submission($meter, $month, $meter->getReadingDay() ?? $place['object']['readingDay']);
                $title = $submission['late'] ? 'Показания переданы с просрочкой: ' : ($submission['submitted'] ? 'Показания переданы: ' : 'Передать показания: ');
                $color = $submission['late'] ? '#d63939' : ($submission['submitted'] ? '#2fb344' : '#f59f00');
                $events[] = ['id' => 'meter-reading-'.$meter->getId().'-'.$due->format('Y-m'), 'title' => $title.$label,
                    'start' => $due->format('Y-m-d'), 'allDay' => true, 'url' => $url, 'color' => $color];
            }

            $state = $this->verifications->state($meter);
            $due = new \DateTimeImmutable($state['nextVerificationDate']);

            if ($due >= $start && $due < $end) {
                $events[] = ['id' => 'meter-verification-'.$meter->getId(), 'title' => 'Поверка: '.$label, 'start' => $due->format('Y-m-d'), 'allDay' => true, 'url' => $url, 'color' => '#d63939'];
            }
        }

        return $events;
    }
}
