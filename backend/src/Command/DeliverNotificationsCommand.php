<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\NotificationDeliveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notifications:deliver', description: 'Доставить очередь уведомлений; push ожидает настройки провайдера.')]
final readonly class DeliverNotificationsCommand
{
    public function __construct(private NotificationDeliveryService $deliveries)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Максимум записей за запуск (1–1000)')]
        int $limit = 100,
        #[Option(description: 'Повторить ранее отложенные каналы после их настройки')]
        bool $retryDeferred = false
    ): int {
        if ($limit < 1 || $limit > 1000) {
            $io->error('Укажите limit от 1 до 1000.');

            return Command::INVALID;
        }
        $io->success(json_encode($this->deliveries->run($limit, $retryDeferred), JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
