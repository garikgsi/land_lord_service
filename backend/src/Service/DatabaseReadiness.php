<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class DatabaseReadiness
{
    public function __construct(private Connection $connection)
    {
    }

    public function isAvailable(): bool
    {
        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
