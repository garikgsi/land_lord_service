<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationControllerTest extends WebTestCase
{
    #[TestWith(['dev', true])]
    #[TestWith(['prod', false])]
    public function testVueShellShowsToolbarOnlyInDevelopment(string $environment, bool $toolbarExpected): void
    {
        $client = new KernelBrowser(static::bootKernel([
            'environment' => $environment,
            'debug' => 'dev' === $environment,
        ]));
        $client->request('GET', '/');

        self::assertSame(
            [200, 1, 1, $toolbarExpected],
            [
                $client->getResponse()->getStatusCode(),
                $client->getCrawler()->filter('#app')->count(),
                $client->getCrawler()->filter('script[type="module"]')->count(),
                $client->getCrawler()->filter('.sf-toolbar')->count() > 0,
            ],
        );
    }
}
