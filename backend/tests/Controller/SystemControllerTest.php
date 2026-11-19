<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SystemControllerTest extends WebTestCase
{
    public function testRendersServerPageWithSharedStyles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/server/status');

        self::assertSame(
            [200, 'Состояние сервиса', true],
            [
                $client->getResponse()->getStatusCode(),
                $client->getCrawler()->filter('h1')->text(),
                $client->getCrawler()->filter('link[rel="stylesheet"][href^="/assets/"]')->count() > 0,
            ],
        );
    }
}
