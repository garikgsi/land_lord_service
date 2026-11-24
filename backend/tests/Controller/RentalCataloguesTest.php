<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\LandLord;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RentalCataloguesTest extends WebTestCase
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

    private function input(array $changes = []): array
    {
        $landLord = new LandLord('Owner');
        $object = new RentalObject($landLord, '100', 'Object', 'Address');
        $renter = new Renter($this->em->find(Ownership::class, 2), 'ИП Арендатор');
        foreach ([$landLord, $object, $renter] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return array_replace([
            'objectId' => $object->getId(), 'renterId' => $renter->getId(), 'contractTypeId' => 1,
            'contractNum' => 'N-1', 'contractDate' => date('Y-m-d'), 'expiryDate' => date('Y-m-d', strtotime('+10 days')),
            'square' => '40', 'monthlyPayment' => '12345.67890123456789', 'securityPayment' => '0',
        ], $changes);
    }

    public function testContactsAllowOnlyNameAndKeepOptionalDataAndHistory(): void
    {
        $input = $this->input();
        $base = '/api/renters/'.$input['renterId'].'/contacts';
        $created = $this->call('POST', $base, ['name' => '  Иван  ']);
        $initialStatus = $this->client->getResponse()->getStatusCode();
        $updated = $this->call('PUT', $base.'/'.$created['id'], [
            'name' => 'Иван', 'lastName' => 'Иванов', 'patronymic' => '',
            'phone' => '+7 (999) 123-45-67', 'email' => ' IVAN@example.com ',
        ]);
        $this->em->clear();
        $read = $this->call('GET', $base.'/'.$created['id']);
        $search = $this->call('GET', $base.'?query=ivan%40example.com');
        $archive = $this->em->getConnection()->fetchAssociative('SELECT name, phone, email FROM renter_contacts_history WHERE id = ?', [$created['id']]);

        self::assertSame(
            [201, 'Иван', null, null, null, 'Иванов', null, '+79991234567', 'ivan@example.com', [$created['id']], ['name' => 'Иван', 'phone' => null, 'email' => null]],
            [$initialStatus, $created['name'], $created['phone'], $created['email'], $created['lastName'],
                $read['lastName'], $read['patronymic'], $read['phone'], $read['email'], array_column($search['items'], 'id'), $archive]
        );
    }

    public function testContactCannotBeReadOrUpdatedThroughAnotherRenter(): void
    {
        $first = $this->input();
        $second = $this->input();
        $created = $this->call('POST', '/api/renters/'.$first['renterId'].'/contacts', ['name' => 'Private']);
        $path = '/api/renters/'.$second['renterId'].'/contacts/'.$created['id'];
        $this->call('GET', $path);
        $readStatus = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', $path, ['name' => 'Wrong owner']);
        $writeStatus = $this->client->getResponse()->getStatusCode();
        $list = $this->call('GET', '/api/renters/'.$second['renterId'].'/contacts');

        self::assertSame([404, 404, []], [$readStatus, $writeStatus, $list['items']]);
    }

    #[DataProvider('invalidContactInputs')]
    public function testContactValidationOnServer(array $body, string $field): void
    {
        $input = $this->input();
        $response = $this->call('POST', '/api/renters/'.$input['renterId'].'/contacts', $body);

        self::assertSame(
            [422, true, 0],
            [$this->client->getResponse()->getStatusCode(), isset($response['errors'][$field]), $this->em->getRepository(\App\Entity\RenterContact::class)->count([])]
        );
    }

    public static function invalidContactInputs(): iterable
    {
        yield [['name' => ' '], 'name'];
        yield [['name' => 'Иван', 'email' => 'wrong'], 'email'];
        yield [['name' => 'Иван', 'phone' => '+7999'], 'phone'];
    }

    public function testEveryAuthenticatedRoleCanCreateAndEditContactsOfAnyAuthor(): void
    {
        $input = $this->input();
        $path = '/api/renters/'.$input['renterId'].'/contacts';
        $original = $this->call('POST', $path, ['name' => 'Created by administrator']);
        $statuses = [];
        foreach ([[], ['ROLE_ACCOUNTANT'], ['ROLE_TECHNICIAN'], ['ROLE_DIRECTOR'], ['ROLE_LAWYER'], ['ROLE_ADMIN'], ['ROLE_SUPER_ADMIN']] as $roles) {
            $token = $this->token($roles);
            $this->call('POST', $path, ['name' => 'New contact'], $token);
            $statuses[] = $this->client->getResponse()->getStatusCode();
            $this->call('PUT', $path.'/'.$original['id'], ['name' => 'Updated by '.implode(',', $roles)], $token);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        self::assertSame(array_merge(...array_fill(0, 7, [201, 200])), $statuses);
    }

    public function testAnonymousCannotCreateOrEditContacts(): void
    {
        $input = $this->input();
        $path = '/api/renters/'.$input['renterId'].'/contacts';
        $contact = $this->call('POST', $path, ['name' => 'Private contact']);
        $this->call('POST', $path, ['name' => 'Anonymous'], '');
        $createStatus = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', $path.'/'.$contact['id'], ['name' => 'Anonymous'], '');

        self::assertSame([401, 401], [$createStatus, $this->client->getResponse()->getStatusCode()]);
    }

    public function testReadOnlyRolesCannotWriteCatalogues(): void
    {
        $statuses = [];
        foreach (['ROLE_ACCOUNTANT', 'ROLE_TECHNICIAN', 'ROLE_DIRECTOR'] as $role) {
            $token = $this->token([$role]);
            foreach (['/api/ownerships', '/api/contract-types'] as $path) {
                $this->call('POST', $path, ['name' => 'Forbidden'], $token);
                $statuses[] = $this->client->getResponse()->getStatusCode();
            }
        }

        self::assertSame(array_fill(0, 6, 403), $statuses);
    }

    public function testLawyerCanCreateEditAndSearchBothCataloguesWithoutChangingIds(): void
    {
        $token = $this->token(['ROLE_LAWYER']);
        $results = [];
        foreach (['ownerships', 'contract-types'] as $kind) {
            $created = $this->call('POST', '/api/'.$kind, ['name' => '  Initial  '], $token);
            $status = $this->client->getResponse()->getStatusCode();
            $updated = $this->call('PUT', '/api/'.$kind.'/'.$created['id'], ['name' => 'Changed catalogue'], $token);
            $read = $this->call('GET', '/api/'.$kind.'/'.$created['id'], token: $token);
            $list = $this->call('GET', '/api/'.$kind.'?query=Changed%20catalogue', token: $token);
            $this->call('POST', '/api/'.$kind, ['name' => '  '], $token);
            $invalidStatus = $this->client->getResponse()->getStatusCode();
            $table = 'ownerships' === $kind ? 'ownerships' : 'contract_types';
            $archived = $this->em->getConnection()->fetchOne('SELECT name FROM '.$table.'_history WHERE id = ?', [$created['id']]);
            $results[] = [201 === $status, $created['id'] === $updated['id'], $read['name'], array_column($list['items'], 'id') === [$created['id']], $archived, $invalidStatus];
        }

        self::assertSame(array_fill(0, 2, [true, true, 'Changed catalogue', true, 'Initial', 422]), $results);
    }

    public function testCatalogueRenamePreservesExistingRenterAndContractRelations(): void
    {
        $input = $this->input();
        $contract = $this->call('POST', '/api/contracts', $input);
        $this->call('PUT', '/api/ownerships/2', ['name' => 'Renamed ownership']);
        $this->call('PUT', '/api/contract-types/1', ['name' => 'Renamed contract type']);
        $this->em->clear();
        $read = $this->call('GET', '/api/contracts/'.$contract['id']);

        self::assertSame(
            [2, 'Renamed ownership', 1, 'Renamed contract type'],
            [$read['renter']['ownership']['id'], $read['renter']['ownership']['name'], $read['contractType']['id'], $read['contractType']['name']]
        );
    }

    public function testPolymorphicColoursIsolateEqualIdsOfDifferentEntityTypesAndKeepHistory(): void
    {
        $owner = $this->call('POST', '/api/land-lords', ['name' => 'Colour owner', 'color' => '#12ABef']);
        $colors = static::getContainer()->get(\App\Service\EntityColorService::class);
        $target = new class($owner['id']) implements \App\Entity\Colorable {
            public function __construct(private int $id)
            {
            }

            public static function colorType(): string
            {
                return 'test_entity';
            }

            public function getId(): ?int
            {
                return $this->id;
            }
        };
        $colors->save($target, '#d63939');
        $other = $colors->color($target);
        $this->call('PUT', '/api/land-lords/'.$owner['id'], ['name' => 'Colour owner', 'color' => null]);
        $this->em->clear();
        $read = $this->call('GET', '/api/land-lords/'.$owner['id']);
        $db = $this->em->getConnection();
        $archive = $db->fetchOne("SELECT h.color FROM entity_colors_history h JOIN entity_colors c ON c.id = h.id WHERE c.entity_type = 'land_lord' AND c.entity_id = ?", [$owner['id']]);
        $columns = $db->fetchFirstColumn("SELECT column_name FROM information_schema.columns WHERE table_name = 'land_lords'");

        self::assertSame(
            ['#12abef', '#d63939', null, '#12abef', false],
            [$owner['color'], $other, $read['color'], $archive, in_array('color', $columns, true)]
        );
    }
}
