<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NotificationListQuery;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\NotificationChannel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class NotificationService
{
    public function __construct(private EntityManagerInterface $em, private NotificationDeliveryService $deliveries)
    {
    }

    /** @param list<NotificationChannel> $channels First creation wins for a deduplication key. */
    public function notify(
        User $recipient,
        string $kind,
        string $title,
        string $message,
        ?\DateTimeImmutable $dueDate = null,
        ?string $sourcePath = null,
        array $channels = [NotificationChannel::Db],
        ?string $deduplicationKey = null
    ): Notification {
        if (null === $recipient->getId() || '' === trim($title) || '' === trim($message) || '' === trim($kind) || strlen($kind) > 80) {
            throw new \InvalidArgumentException('A persisted recipient, kind, title and message are required.');
        }
        if (null !== $deduplicationKey && ('' === $deduplicationKey || strlen($deduplicationKey) > 190)) {
            throw new \InvalidArgumentException('Invalid notification deduplication key.');
        }
        if (null !== $sourcePath && (strlen($sourcePath) > 255 || !preg_match('~^/(?!/)[a-zA-Z0-9/_-]*$~D', $sourcePath))) {
            throw new \InvalidArgumentException('The source must be an internal application path.');
        }
        if ([] === $channels || array_any($channels, static fn ($channel): bool => !$channel instanceof NotificationChannel)) {
            throw new \InvalidArgumentException('Choose supported notification channels.');
        }

        return $this->em->getConnection()->transactional(function () use ($recipient, $kind, $title, $message, $dueDate, $sourcePath, $channels, $deduplicationKey): Notification {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $timestamp = $now->format('Y-m-d H:i:s.u');
            $id = $this->em->getConnection()->fetchOne(
                'INSERT INTO notifications (user_id, kind, title, message, source_path, deduplication_key, due_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (user_id, deduplication_key) DO NOTHING RETURNING id',
                [$recipient->getId(), $kind, trim($title), trim($message), $sourcePath, $deduplicationKey, $dueDate?->format('Y-m-d'), $timestamp],
            );
            if (false === $id) {
                $id = $this->em->getConnection()->fetchOne('SELECT id FROM notifications WHERE user_id = ? AND deduplication_key = ?', [$recipient->getId(), $deduplicationKey]);
            } else {
                $this->deliveries->enqueue((int) $id, $channels, $now);
            }

            return $this->em->find(Notification::class, (int) $id);
        });
    }

    public function present(Notification $record): array
    {
        return [
            'id' => $record->getId(), 'kind' => $record->getKind(),
            'title' => $record->getTitle(), 'message' => $record->getMessage(),
            'sourcePath' => $record->getSourcePath(), 'dueDate' => $record->getDueDate()?->format('Y-m-d'),
            'createdAt' => $record->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'readAt' => $record->getReadAt()?->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    public function owned(int $id, User $user): Notification
    {
        $record = $this->em->getRepository(Notification::class)->findOneBy(['id' => $id, 'user' => $user])
            ?? throw new NotFoundHttpException('Уведомление не найдено.');
        $this->em->refresh($record);

        return $record;
    }

    public function unreadCount(User $user): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$user->getId()]);
    }

    public function list(User $user, NotificationListQuery $input): array
    {
        $query = $this->em->getRepository(Notification::class)->createQueryBuilder('n')
            ->andWhere('n.user = :user')->setParameter('user', $user);
        if ('unread' === $input->status) {
            $query->andWhere('n.readAt IS NULL');
        }
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(n.title) LIKE :query OR LOWER(n.message) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $total = (int) (clone $query)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('n.createdAt', 'DESC')->addOrderBy('n.id', 'DESC')
            ->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();

        return ['items' => array_map($this->present(...), $items), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function markRead(int $id, User $user): Notification
    {
        $record = $this->owned($id, $user);
        $this->em->getConnection()->executeStatement(
            "UPDATE notifications SET read_at = clock_timestamp() AT TIME ZONE 'UTC' WHERE id = ? AND user_id = ? AND read_at IS NULL",
            [$id, $user->getId()],
        );
        $this->em->refresh($record);

        return $record;
    }

    public function markAllRead(User $user): void
    {
        $this->em->getConnection()->executeStatement(
            "UPDATE notifications SET read_at = clock_timestamp() AT TIME ZONE 'UTC' WHERE user_id = ? AND read_at IS NULL",
            [$user->getId()],
        );
    }

    public function calendar(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $records = $this->em->getRepository(Notification::class)->createQueryBuilder('n')
            ->andWhere('n.user = :user AND n.dueDate >= :start AND n.dueDate < :end')
            ->setParameter('user', $user)->setParameter('start', $start)->setParameter('end', $end)
            ->orderBy('n.dueDate', 'ASC')->addOrderBy('n.id', 'ASC')->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();

        return array_map(static fn (Notification $record): array => [
            'id' => 'notification-'.$record->getId(), 'title' => 'Уведомление: '.$record->getTitle(),
            'start' => $record->getDueDate()->format('Y-m-d'), 'allDay' => true,
            'url' => '/notifications/'.$record->getId(),
            'color' => null === $record->getReadAt() ? '#206bc4' : '#2fb344',
        ], $records);
    }
}
