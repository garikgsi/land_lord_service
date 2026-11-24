<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\NotificationDelivery;

final readonly class PushNotificationSender implements NotificationSenderInterface
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Push;
    }

    public function send(NotificationDelivery $delivery): void
    {
        throw new ChannelUnavailable('Push-провайдер и регистрация устройств ещё не подключены.');
    }
}
