<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ContractNotificationService;
use App\Service\MeterNotificationService;
use App\Service\UnityProviderContractNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notifications:generate', description: 'Создать уведомления по договорам и срокам показаний согласно notification_types. Повторный запуск не создаёт дубли.')]
final readonly class GenerateNotificationsCommand
{
    public function __construct(private ContractNotificationService $contracts, private MeterNotificationService $meters, private UnityProviderContractNotificationService $suppliers)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success('Сформировано уведомлений: '.($this->contracts->generate() + $this->meters->generate() + $this->suppliers->generate()));

        return Command::SUCCESS;
    }
}
