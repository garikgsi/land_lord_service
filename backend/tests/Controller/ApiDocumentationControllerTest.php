<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Security\Role;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocumentationControllerTest extends WebTestCase
{
    public function testOpenApiCoversEveryImplementedApiMethod(): void
    {
        $client = static::createClient();
        $client->request('GET', '/server/api/openapi.json');
        $doc = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $expected = [];
        foreach (static::getContainer()->get('router')->getRouteCollection() as $route) {
            if (str_starts_with($route->getPath(), '/api/')) {
                foreach ($route->getMethods() as $method) {
                    $expected[] = $method.' '.$route->getPath();
                }
            }
        }
        $actual = [];
        foreach ($doc['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    $actual[] = strtoupper($method).' '.$path;
                    if (empty($operation['summary']) || empty($operation['responses'])) {
                        self::fail('Undocumented operation: '.$path);
                    }
                }
            }
        }
        sort($actual);
        sort($expected);
        self::assertSame([200, $expected], [$client->getResponse()->getStatusCode(), $actual]);
    }

    public function testSpecificationDescribesAuthenticationRequestsAndSafeUserResponses(): void
    {
        $client = static::createClient();
        $client->request('GET', '/server/api/openapi.json');
        $doc = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $schemas = $doc['components']['schemas'];
        self::assertSame(
            [[['Bearer' => []]], [], [['InvitationToken' => []]], ['email', 'firstName'], 10, '^[a-f0-9]{64}$', false, Role::values(), true, false, Role::values()],
            [$doc['security'], $doc['paths']['/api/auth/login']['post']['security'], $doc['paths']['/api/auth/invitation/avatar']['get']['security'], $schemas['UserInput']['required'], $schemas['ActivationInput']['properties']['password']['minLength'], $schemas['ActivationInput']['properties']['token']['pattern'], isset($schemas['User']['properties']['password']), $schemas['UserInput']['properties']['roles']['items']['enum'], isset($schemas['UserInput']['properties']['phone']['pattern']), $schemas['UserInput']['properties']['firstName']['nullable'] ?? false, $schemas['RoleList']['properties']['items']['items']['properties']['code']['enum']],
        );
    }

    public function testSwaggerUiIsAvailableWithOfflineAssets(): void
    {
        $client = static::createClient();
        $client->request('GET', '/server/api');
        self::assertSame([200, 1, 0], [$client->getResponse()->getStatusCode(), $client->getCrawler()->filter('#swagger-data')->count(), $client->getCrawler()->filter('script[src^="http"], link[href^="http"]')->count()]);
    }
}
