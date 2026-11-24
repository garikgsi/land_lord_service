<?php

declare(strict_types=1);

namespace App\Entity;

use App\Notification\NotificationChannel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_deliveries')]
#[ORM\UniqueConstraint(name: 'uniq_notification_deliveries_channel', columns: ['notification_id', 'channel'])]
#[ORM\Index(name: 'idx_notification_deliveries_queue', columns: ['status', 'next_attempt_at'])]
class NotificationDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'notification_id', nullable: false, onDelete: 'RESTRICT')]
        private Notification $notification,
        #[ORM\Column(length: 10, enumType: NotificationChannel::class)]
        private NotificationChannel $channel,
        #[ORM\Column(length: 12)]
        private string $status,
        #[ORM\Column(name: 'attempt_count')]
        private int $attemptCount,
        #[ORM\Column(name: 'next_attempt_at', type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE')]
        private \DateTimeImmutable $nextAttemptAt,
        #[ORM\Column(name: 'locked_until', type: Types::DATETIME_IMMUTABLE, nullable: true, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE')]
        private ?\DateTimeImmutable $lockedUntil = null,
        #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE')]
        private ?\DateTimeImmutable $sentAt = null,
        #[ORM\Column(name: 'last_error', type: Types::TEXT, nullable: true)]
        private ?string $lastError = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }
}
