<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\Contract;
use App\Entity\ContractType;
use App\Entity\LandLord;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\Space;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RentalRegistryTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('A separate test database is required.');
        }
        $this->em->getConnection()->beginTransaction();
        $this->token = $this->token(['ROLE_ADMIN']);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function token(array $roles): string
    {
        $user = new User(bin2hex(random_bytes(8)).'@property.test');
        $user->setRoles($roles);
        $user->setActive(true);
        $token = bin2hex(random_bytes(32));
        $this->em->persist($user);
        $this->em->persist(new AccessToken($user, $token, new \DateTimeImmutable('+1 hour')));
        $this->em->flush();

        return $token;
    }

    private function call(string $method, string $path, ?array $body = null, ?string $token = null): array
    {
        $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.($token ?? $this->token)], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function landLord(): array
    {
        return $this->call('POST', '/api/land-lords', ['name' => ' ИП Тестовый ']);
    }

    public function testCreateReadSearchAndUpdatePreserveExactAreaAndUseServerDefault(): void
    {
        $landLord = $this->landLord();
        $input = ['name' => ' Офис ', 'address' => ' Адрес ', 'square' => '123.12345678901234567890123456789', 'landLordId' => $landLord['id']];
        $created = $this->call('POST', '/api/objects', $input);
        $status = $this->client->getResponse()->getStatusCode();
        $input['name'] = 'Новый офис';
        $input['readingDay'] = 20;
        $this->call('PUT', '/api/objects/'.$created['id'], $input);
        $read = $this->call('GET', '/api/objects/'.$created['id']);
        $list = $this->call('GET', '/api/objects?query='.urlencode('Тестовый'));

        self::assertSame([201, 25, 'Новый офис', 'Адрес', $input['square'], 20, 1, $landLord['id']], [$status, $created['readingDay'], $read['name'], $read['address'], $read['square'], $read['readingDay'], $list['total'], $read['landLord']['id']]);
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidObjectIsRejectedWithFieldError(string $field, mixed $value): void
    {
        $input = ['name' => 'Офис', 'address' => 'Адрес', 'square' => '100', 'landLordId' => $this->landLord()['id']];
        $input[$field] = $value;
        $body = $this->call('POST', '/api/objects', $input);

        self::assertSame([422, true, 0], [$this->client->getResponse()->getStatusCode(), isset($body['errors'][$field]), $this->em->getRepository(RentalObject::class)->count([])]);
    }

    public static function invalidInputs(): iterable
    {
        yield 'blank name' => ['name', '   '];
        yield 'blank address' => ['address', '  '];
        yield 'zero area' => ['square', '0.00'];
        yield 'negative area' => ['square', '-1'];
        yield 'float area must be a string' => ['square', 1.25];
        yield 'integer area must be a string' => ['square', 100];
        yield 'text area' => ['square', 'NaN'];
        yield 'day zero' => ['readingDay', 0];
        yield 'day too large' => ['readingDay', 32];
        yield 'missing landlord' => ['landLordId', 0];
    }

    #[DataProvider('roles')]
    public function testRolesControlWritesButAllowReading(string $role, int $status): void
    {
        $token = $this->token([$role]);
        $this->call('POST', '/api/land-lords', ['name' => 'Контрагент'], $token);
        $write = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/objects', token: $token);

        self::assertSame([$status, 200], [$write, $this->client->getResponse()->getStatusCode()]);
    }

    public static function roles(): iterable
    {
        yield ['ROLE_LAWYER', 201];
        yield ['ROLE_ADMIN', 201];
        yield ['ROLE_DIRECTOR', 403];
        yield ['ROLE_ACCOUNTANT', 403];
        yield ['ROLE_TECHNICIAN', 403];
    }

    public function testLandLordColorPersistsAndCanBeReset(): void
    {
        $created = $this->call('POST', '/api/land-lords', ['name' => 'Color test', 'color' => '#12AbEf']);
        $this->em->clear();
        $read = $this->call('GET', '/api/land-lords/'.$created['id']);
        $object = $this->call('POST', '/api/objects', ['name' => 'Object', 'address' => 'Address', 'square' => '10', 'landLordId' => $created['id']]);
        $reset = $this->call('PUT', '/api/land-lords/'.$created['id'], ['name' => 'Color test', 'color' => null]);

        self::assertSame(['#12abef', '#12abef', '#12abef', null], [$created['color'] ?? 'missing', $read['color'] ?? 'missing', $object['landLord']['color'] ?? 'missing', $reset['color']]);
    }

    #[DataProvider('invalidColors')]
    public function testInvalidLandLordColorIsRejected(mixed $color): void
    {
        $body = $this->call('POST', '/api/land-lords', ['name' => 'Color test', 'color' => $color]);

        self::assertSame([422, true, 0], [$this->client->getResponse()->getStatusCode(), isset($body['errors']['color']), $this->em->getRepository(LandLord::class)->count([])]);
    }

    public static function invalidColors(): iterable
    {
        yield ['red'];
        yield ['#123'];
        yield ['#12345678'];
        yield [''];
        yield ['#gggggg'];
        yield ['#123456; color: red'];
    }

    public function testLandLordSupportsUpdateAndValidation(): void
    {
        $landLord = $this->landLord();
        $this->call('PUT', '/api/land-lords/'.$landLord['id'], ['name' => 'Новое имя']);
        $changed = $this->call('GET', '/api/land-lords/'.$landLord['id']);
        $body = $this->call('PUT', '/api/land-lords/'.$landLord['id'], ['name' => ' ']);

        self::assertSame(['Новое имя', 422, true], [$changed['name'], $this->client->getResponse()->getStatusCode(), isset($body['errors']['name'])]);
    }

    public function testUnknownLandLordAndMissingObjectReturnUsefulErrors(): void
    {
        $this->call('POST', '/api/objects', ['name' => 'Test', 'address' => 'Address', 'square' => '1', 'landLordId' => 2147483647]);
        $unknown = $this->client->getResponse()->getStatusCode();
        $this->call('GET', '/api/objects/2147483647');

        self::assertSame([422, 404], [$unknown, $this->client->getResponse()->getStatusCode()]);
    }

    public function testMeterSettingsUpdateOnlyTheReadingDay(): void
    {
        $landLord = $this->landLord();
        $object = $this->call('POST', '/api/objects', ['name' => 'Объект', 'address' => 'Адрес', 'square' => '100.25', 'landLordId' => $landLord['id']]);
        $updated = $this->call('PUT', '/api/objects/'.$object['id'].'/meter-settings', ['readingDay' => 18]);
        $status = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/objects/'.$object['id'].'/meter-settings', ['readingDay' => 19], $this->token(['ROLE_DIRECTOR']));

        self::assertSame([200, 18, 'Объект', 'Адрес', '100.25', 403], [$status, $updated['readingDay'], $updated['name'], $updated['address'], $updated['square'], $this->client->getResponse()->getStatusCode()]);
    }

    #[DataProvider('invalidMeterSettings')]
    public function testInvalidMeterSettingsAreRejected(array $input): void
    {
        $object = $this->call('POST', '/api/objects', ['name' => 'Объект', 'address' => 'Адрес', 'square' => '100', 'landLordId' => $this->landLord()['id']]);
        $body = $this->call('PUT', '/api/objects/'.$object['id'].'/meter-settings', $input);

        self::assertSame([422, true, 25], [$this->client->getResponse()->getStatusCode(), isset($body['errors']['readingDay']), $this->em->find(RentalObject::class, $object['id'])->getReadingDay()]);
    }

    public static function invalidMeterSettings(): iterable
    {
        yield 'missing' => [[]];
        yield 'zero' => [['readingDay' => 0]];
        yield 'too large' => [['readingDay' => 32]];
    }

    public function testRegistryRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/objects');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testChangingLandLordRequiresEndingUnfinishedContracts(): void
    {
        $old = new LandLord('Старый');
        $new = new LandLord('Новый');
        $object = new RentalObject($old, '100', 'Объект', 'Адрес');
        $space = new Space($object, 'Space', '10');
        $renter = new Renter($this->em->find(Ownership::class, 1), 'Арендатор');
        $contract = new Contract($space, $renter, $old, $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('today'), 'N1', '1000.01');
        foreach ([$old, $new, $object, $space, $renter, $contract] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->call('PUT', '/api/objects/'.$object->getId(), ['name' => 'Объект', 'address' => 'Адрес', 'square' => '100', 'landLordId' => $new->getId()]);

        self::assertSame([409, $old->getId()], [$this->client->getResponse()->getStatusCode(), $object->getLandLord()->getId()]);
    }
}
