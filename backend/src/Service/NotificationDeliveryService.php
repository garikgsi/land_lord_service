<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationDelivery;
use App\Notification\ChannelUnavailable;
use App\Notification\NotificationChannel;
use App\Notification\NotificationSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class NotificationDeliveryService
{
    private const MAX_ATTEMPTS = 5;
    private const RETRY_DELAYS_SECONDS = [60, 300, 900, 3600];

    /** @param iterable<NotificationSenderInterface> $senders */
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        #[AutowireIterator('app.notification_sender')]
        private iterable $senders,
    ) {
    }

    /** @param list<NotificationChannel> $channels Enqueue atomically with the notification. */
    public function enqueue(int $notificationId, array $channels, \DateTimeImmutable $at): void
    {
        $timestamp = $at->format('Y-m-d H:i:s.u');
        foreach (array_unique(array_map(static fn (NotificationChannel $channel): string => $channel->value, $channels)) as $channel) {
            $database = NotificationChannel::Db->value === $channel;
            $this->em->getConnection()->insert('notification_deliveries', [
                'notification_id' => $notificationId, 'channel' => $channel,
                'status' => $database ? 'sent' : 'pending', 'attempt_count' => 0,
                'next_attempt_at' => $timestamp, 'sent_at' => $database ? $timestamp : null,
            ]);
        }
    }

    /** Deliver outside the claiming transaction; leases allow a crashed worker to be resumed. */
    public function run(int $limit = 100, bool $retryDeferred = false): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'deferred' => 0];
        for ($index = 0; $index < $limit; ++$index) {
            $deferred = $retryDeferred ? " OR (status = 'deferred' AND next_attempt_at <= (clock_timestamp() AT TIME ZONE 'UTC'))" : '';
            $claim = $this->em->getConnection()->fetchAssociative(
                "UPDATE notification_deliveries SET status = 'processing', attempt_count = attempt_count + 1, locked_until = (clock_timestamp() AT TIME ZONE 'UTC') + INTERVAL '5 minutes' WHERE id = (SELECT id FROM notification_deliveries WHERE attempt_count < ? AND ((status IN ('pending', 'failed') AND next_attempt_at <= (clock_timestamp() AT TIME ZONE 'UTC')) OR (status = 'processing' AND locked_until <= (clock_timestamp() AT TIME ZONE 'UTC'))".$deferred.') ORDER BY next_attempt_at, id FOR UPDATE SKIP LOCKED LIMIT 1) RETURNING id, attempt_count',
                [self::MAX_ATTEMPTS],
            );
            if (false === $claim) {
                break;
            }
            $delivery = $this->em->find(NotificationDelivery::class, (int) $claim['id']);
            $this->em->refresh($delivery);
            $status = 'sent';
            $error = null;
            try {
                $user = $delivery->getNotification()->getUser();
                $this->em->refresh($user);
                if (!$user->isActive() || $user->isBlocked()) {
                    throw new ChannelUnavailable('Получатель неактивен или заблокирован.');
                }
                $sender = null;
                foreach ($this->senders as $candidate) {
                    if ($candidate->channel() === $delivery->getChannel()) {
                        $sender = $candidate;
                        break;
                    }
                }
                if (null === $sender) {
                    throw new ChannelUnavailable('Канал доставки пока не подключён.');
                }
                $sender->send($delivery);
            } catch (ChannelUnavailable $exception) {
                $status = 'deferred';
                $error = $exception->getMessage();
            } catch (\Throwable $exception) {
                $status = 'failed';
                $error = 'Ошибка доставки. См. журнал по ID доставки.';
                $this->logger->error('Notification delivery failed.', ['delivery_id' => $delivery->getId(), 'exception_class' => $exception::class]);
            }
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $delay = self::RETRY_DELAYS_SECONDS[min((int) $claim['attempt_count'] - 1, count(self::RETRY_DELAYS_SECONDS) - 1)];
            $this->em->getConnection()->executeStatement(
                'UPDATE notification_deliveries SET status = ?, locked_until = NULL, sent_at = ?, last_error = ?, next_attempt_at = ?, attempt_count = attempt_count - ? WHERE id = ? AND status = ? AND attempt_count = ?',
                [$status, 'sent' === $status ? $now->format('Y-m-d H:i:s.u') : null, $error, $now->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s.u'), 'deferred' === $status ? 1 : 0, $claim['id'], 'processing', $claim['attempt_count']],
            );
            ++$result[$status];
        }

        return $result;
    }
}
