<?php

declare(strict_types=1);

namespace App\Notification;

enum NotificationChannel: string
{
    case Db = 'db';
    case Email = 'email';
    case Push = 'push';
}
