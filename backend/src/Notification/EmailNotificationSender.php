<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\NotificationDelivery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class EmailNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(bool:NOTIFICATION_EMAIL_ENABLED)%')]
        private bool $enabled,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
        #[Autowire('%env(MAIL_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }

    public function send(NotificationDelivery $delivery): void
    {
        if (!$this->enabled || str_starts_with($this->mailerDsn, 'null:')) {
            throw new ChannelUnavailable('Канал email отключён или SMTP не настроен.');
        }
        $notification = $delivery->getNotification();
        $this->mailer->send((new Email())
            ->from($this->mailFrom)->to($notification->getUser()->getEmail())
            ->subject($notification->getTitle())
            ->text($notification->getMessage()."\n\n".rtrim($this->frontendUrl, '/').'/notifications/'.$notification->getId()));
    }
}
