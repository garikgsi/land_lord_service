<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testReportsHealthyDatabase(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/healthz');

        self::assertSame(
            [200, '{"status":"ok"}'],
            [$client->getResponse()->getStatusCode(), $client->getResponse()->getContent()],
        );
    }

    public function testReportsUnavailableDatabaseWithoutExposingDetails(): void
    {
        $client = static::createClient();
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException(
            new class('Private database connection details') extends \RuntimeException implements Exception {},
        );
        static::getContainer()->set('doctrine.dbal.default_connection', $connection);
        $client->request('GET', '/api/healthz');

        self::assertSame(
            [503, '{"status":"unavailable"}'],
            [$client->getResponse()->getStatusCode(), $client->getResponse()->getContent()],
        );
    }
}
