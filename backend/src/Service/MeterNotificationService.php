<?php

declare(strict_types=1);

namespace App\Service;

use App\Notification\NotificationChannel;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MeterNotificationService
{
    /** @param list<string> $channels */
    public function __construct(
        private EntityManagerInterface $em,
        private MeterScheduleService $schedule,
        private NotificationService $notifications,
        private NotificationTypeService $types,
        #[Autowire('%env(csv:NOTIFICATION_CHANNELS)%')]
        private array $channels,
    ) {
    }

    public function generate(?\DateTimeImmutable $today = null): int
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $type = $this->types->configuration(NotificationKind::MeterReading);
        if (null === $type || !$type->isActive()) {
            return 0;
        }
        $windowEnd = $today->modify('+'.$type->getDays().' days');
        $recipients = $this->types->recipients($type);
        if ([] === $recipients) {
            return 0;
        }

        $channels = array_map(static fn (string $channel): NotificationChannel => NotificationChannel::from(trim($channel)), $this->channels);
        $groups = [];

        for ($month = $today->modify('first day of this month'); $month <= $windowEnd; $month = $month->modify('+1 month')) {
            $records = $this->em->getConnection()->fetchAllAssociative(
                'SELECT m.id, m.create_date, COALESCE(m.reading_day, o.reading_day) AS reading_day, o.id AS object_id, o.name AS object_name, u.id AS utility_id, u.name AS utility_name
                 FROM meters m
                 JOIN unities u ON u.id = m.utility_id
                 LEFT JOIN object_meters om ON om.meter_id = m.id
                 LEFT JOIN space_meters sm ON sm.meter_id = m.id
                 LEFT JOIN spaces s ON s.id = sm.space_id
                 JOIN objects o ON o.id = COALESCE(om.object_id, s.object_id)
                 WHERE NOT m.is_deleted AND NOT o.is_deleted AND (s.id IS NULL OR NOT s.is_deleted) AND NOT EXISTS (SELECT 1 FROM meter_readings r WHERE r.meter_id = m.id AND r.meter_reading_date >= ? AND r.meter_reading_date < ?)
                 ORDER BY o.id, u.name, u.id, m.id',
                [$month->format('Y-m-d'), $month->modify('+1 month')->format('Y-m-d')],
            );

            foreach ($records as $record) {
                $due = $this->schedule->deadline($month, (int) $record['reading_day']);

                if ($due < $today || $due > $windowEnd || $record['create_date'] > $due->format('Y-m-d')) {
                    continue;
                }

                $key = 'meter.reading:'.$record['object_id'].':'.$due->format('Y-m-d');
                $groups[$key] ??= ['objectId' => (int) $record['object_id'], 'name' => $record['object_name'], 'due' => $due, 'utilities' => []];
                $groups[$key]['utilities'][$record['utility_id']] ??= ['name' => $record['utility_name'], 'count' => 0];
                ++$groups[$key]['utilities'][$record['utility_id']]['count'];
            }
        }

        $created = 0;

        foreach ($groups as $key => $group) {
            $lines = array_map(static fn (array $utility): string => $utility['name'].' — '.$utility['count'], array_values($group['utilities']));
            $title = 'Передача показаний: '.$group['name'];
            $message = 'Объект: '.$group['name'].".\nСрок передачи показаний: ".$group['due']->format('d.m.Y').".\nСчётчики без показаний за ".$group['due']->format('m.Y').":\n".implode("\n", $lines);

            foreach ($recipients as $recipient) {
                if (false !== $this->em->getConnection()->fetchOne('SELECT id FROM notifications WHERE user_id = ? AND deduplication_key = ?', [$recipient->getId(), $key])) {
                    continue;
                }

                $this->notifications->notify(
                    $recipient,
                    'meter.reading',
                    $title,
                    $message,
                    $group['due'],
                    '/objects/'.$group['objectId'],
                    $channels,
                    $key,
                );
                ++$created;
            }
        }

        return $created;
    }
}
