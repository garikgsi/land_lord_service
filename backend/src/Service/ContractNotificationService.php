<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Notification\NotificationChannel;
use App\Notification\NotificationKind;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ContractNotificationService
{
    /** @param list<string> $channels */
    public function __construct(
        private EntityManagerInterface $em,
        private ContractService $contracts,
        private NotificationService $notifications,
        private NotificationTypeService $types,
        #[Autowire('%env(csv:NOTIFICATION_CHANNELS)%')]
        private array $channels,
    ) {
    }

    public function notificationDate(\DateTimeImmutable $executionDate, NotificationKind $kind = NotificationKind::ContractExpiry): \DateTimeImmutable
    {
        $type = $this->types->configuration($kind) ?? throw new \LogicException('Notification type is missing.');

        return $this->types->notificationDate($type, $executionDate);
    }

    public function generate(?\DateTimeImmutable $today = null): int
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $channels = array_map(static fn (string $channel): NotificationChannel => NotificationChannel::from(trim($channel)), $this->channels);
        $settings = [];
        $recipients = [];
        foreach ([NotificationKind::ContractExpiry, NotificationKind::ContractIndexation, NotificationKind::UnsignedContract] as $kind) {
            $type = $this->types->configuration($kind);
            if (null !== $type && $type->isActive()) {
                $settings[$kind->code()] = $type;
                $recipients[$kind->code()] = $this->types->recipients($type);
            }
        }
        if ([] === $settings) {
            return 0;
        }
        $records = $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->andWhere('c.expiryDate >= :today')->andWhere('c.annulmentDate IS NULL OR c.annulmentDate >= :today')
            ->setParameter('today', $today)->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
        $created = 0;
        foreach ($records as $record) {
            $end = $this->contracts->effectiveEnd($record);
            if ($end < $record->getContractDate()) {
                continue;
            }
            $events = [['kind' => 'contract.expiry', 'date' => $end, 'title' => 'Заканчивается договор № '.$record->getContractNum()]];
            $unsignedType = $settings[NotificationKind::UnsignedContract->code()] ?? null;
            if (null !== $unsignedType && !$record->isSigned() && $record->getContractDate() <= $today) {
                $elapsedDays = (int) $record->getContractDate()->diff($today)->days;
                $periodDays = intdiv($elapsedDays, $unsignedType->getDays()) * $unsignedType->getDays();
                $occurrence = $record->getContractDate()->modify('+'.$periodDays.' days');
                $events[] = [
                    'kind' => NotificationKind::UnsignedContract->code(),
                    'date' => $occurrence,
                    'title' => 'Не подписан договор аренды № '.$record->getContractNum(),
                    'message' => 'Аренда началась '.$record->getContractDate()->format('d.m.Y').', но договор № '.$record->getContractNum().' ещё не подписан.',
                ];
            }
            $anchor = $record->getIncreaseDate();
            if (null !== $anchor && $anchor != $record->getLastIndexationDate() && $anchor >= $today && $anchor >= $record->getContractDate() && $anchor <= $end) {
                $events[] = ['kind' => 'contract.indexation', 'date' => $anchor, 'title' => 'Индексация договора № '.$record->getContractNum()];
            }
            $indexationType = $settings['contract.indexation'] ?? null;
            $reminder = null === $indexationType ? null : $this->contracts->reminderDate($record, $today);
            while (null !== $reminder && $this->types->notificationDate($indexationType, $reminder) <= $today) {
                $events[] = ['kind' => 'contract.indexation', 'date' => $reminder, 'title' => 'Индексация договора № '.$record->getContractNum()];
                $reminder = $this->contracts->reminderDate($record, $reminder->modify('+1 day'));
            }
            foreach ($events as $event) {
                $type = $settings[$event['kind']] ?? null;
                if (null === $type || (NotificationKind::UnsignedContract->value !== $type->getId() && $this->types->notificationDate($type, $event['date']) > $today)) {
                    continue;
                }
                $key = $event['kind'].':'.$record->getId().':'.$event['date']->format('Y-m-d');
                foreach ($recipients[$event['kind']] as $recipient) {
                    if (false !== $this->em->getConnection()->fetchOne('SELECT id FROM notifications WHERE user_id = ? AND deduplication_key = ?', [$recipient->getId(), $key])) {
                        continue;
                    }
                    $this->notifications->notify(
                        $recipient,
                        $event['kind'],
                        $event['title'],
                        ($event['message'] ?? $event['title'].'. Дата: '.$event['date']->format('d.m.Y').'.').' Объект: '.$record->getSpace()->getObject()->getName().'. Арендатор: '.$record->getRenter()->getName().'.',
                        $event['date'],
                        '/contracts/'.$record->getId(),
                        $channels,
                        $key,
                    );
                    ++$created;
                }
            }
        }

        return $created;
    }
}
