<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\NotificationDelivery;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.notification_sender')]
interface NotificationSenderInterface
{
    public function channel(): NotificationChannel;

    public function send(NotificationDelivery $delivery): void;
}
