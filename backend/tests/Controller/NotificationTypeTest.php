<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\LandLord;
use App\Entity\Meter;
use App\Entity\ObjectMeter;
use App\Entity\RentalObject;
use App\Entity\Unity;
use App\Entity\User;
use App\Service\MeterNotificationService;
use App\Service\NotificationTypeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NotificationTypeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) { throw new \LogicException('Test database required.'); }
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function user(array $roles): User
    {
        $user = new User(bin2hex(random_bytes(8)).'@settings.test');
        $user->setRoles($roles);
        $user->setActive(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function call(string $method, string $path, ?User $user, ?array $body = null): int
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->em->persist(new AccessToken($this->em->find(User::class, $user->getId()), $token, new \DateTimeImmutable('+1 hour')));
            $this->em->flush();
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $path, server: $headers, content: $body ? json_encode($body, JSON_THROW_ON_ERROR) : null);

        return $this->client->getResponse()->getStatusCode();
    }

    public function testSeededTypesUseAgreedDaysAndRoles(): void
    {
        $rows = static::getContainer()->get(NotificationTypeService::class)->list()['items'];
        self::assertSame([[1, 2, 3, 4, 5], [30, 30, 7, 30, 7], [['ROLE_LAWYER'], ['ROLE_LAWYER'], ['ROLE_TECHNICIAN'], ['ROLE_LAWYER'], ['ROLE_LAWYER', 'ROLE_DIRECTOR']], [true, true, true, true, true]], [array_column($rows, 'id'), array_column($rows, 'days'), array_column($rows, 'roles'), array_column($rows, 'isActive')]);
    }

    public function testSettingsAreRestrictedToAdminAndDirectorIncludingWrites(): void
    {
        $statuses = [];
        $body = ['name' => 'Settings', 'days' => 0, 'roles' => [], 'isActive' => false];
        foreach ([['ROLE_ADMIN'], ['ROLE_SUPER_ADMIN'], ['ROLE_DIRECTOR'], ['ROLE_LAWYER'], ['ROLE_TECHNICIAN']] as $roles) {
            $user = $this->user($roles);
            $statuses[] = $this->call('GET', '/api/notification-types', $user);
            $statuses[] = $this->call('PUT', '/api/notification-types/1', $user, $body);
        }
        $statuses[] = $this->call('GET', '/api/notification-types', null);
        self::assertSame([200, 200, 200, 200, 200, 200, 403, 403, 403, 403, 401], $statuses);
    }

    public function testInvalidSettingsAreRejectedAndZeroDaysIsAllowed(): void
    {
        $user = $this->user(['ROLE_DIRECTOR']);
        $base = ['name' => 'Settings', 'days' => 0, 'roles' => ['ROLE_LAWYER'], 'isActive' => true];
        $statuses = [$this->call('PUT', '/api/notification-types/1', $user, $base)];
        foreach ([['days' => -1], ['roles' => ['ROLE_UNKNOWN']], ['roles' => [123]], ['name' => ' ']] as $changes) { $statuses[] = $this->call('PUT', '/api/notification-types/1', $user, array_replace($base, $changes)); }
        self::assertSame([200, 422, 422, 422, 422], $statuses);
    }

    public function testRepeatIntervalMustBePositiveButLeadTimeCanBeZero(): void
    {
        $user = $this->user(['ROLE_DIRECTOR']);
        $input = ['name' => 'Repeat', 'days' => 0, 'roles' => ['ROLE_LAWYER', 'ROLE_DIRECTOR'], 'isActive' => true];
        $invalid = $this->call('PUT', '/api/notification-types/5', $user, $input);
        $saved = $this->call('PUT', '/api/notification-types/5', $user, [...$input, 'days' => 7]);
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $lead = $this->call('PUT', '/api/notification-types/1', $user, $input);

        self::assertSame([422, 200, 'repeat', 7, 200], [$invalid, $saved, $body['timing'], $body['days'], $lead]);
    }

    public function testConfigurationIsReadAgainAndMeterGeneratorUsesChangedRolesDaysAndSwitch(): void
    {
        $director = $this->user(['ROLE_DIRECTOR', 'ROLE_ACCOUNTANT']);
        $this->user(['ROLE_TECHNICIAN']);
        $owner = new LandLord('Owner');
        $object = new RentalObject($owner, '100', 'Object', 'Address');
        $unity = new Unity('Water', true);
        $meter = new Meter($unity, new \DateTimeImmutable('2020-01-01'), 0, 365);
        foreach ([$owner, $object, $unity, $meter, new ObjectMeter($object, $meter)] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE notification_types SET days = 2, roles = ? WHERE id = 3', ['["ROLE_DIRECTOR","ROLE_ACCOUNTANT"]']);
        $generator = static::getContainer()->get(MeterNotificationService::class);
        $early = $generator->generate(new \DateTimeImmutable('2026-11-22'));
        $db->executeStatement('UPDATE notification_types SET days = 3 WHERE id = 3');
        $due = $generator->generate(new \DateTimeImmutable('2026-11-22'));
        $again = $generator->generate(new \DateTimeImmutable('2026-11-22'));
        $db->executeStatement('UPDATE notification_types SET is_active = false WHERE id = 3');
        $this->user(['ROLE_DIRECTOR']);
        $disabled = $generator->generate(new \DateTimeImmutable('2026-11-22'));
        self::assertSame([0, 1, 0, 0, [$director->getId()]], [$early, $due, $again, $disabled, array_map('intval', $db->fetchFirstColumn('SELECT user_id FROM notifications'))]);
    }

    public function testDashboardAccessAndCleanRoutesDoNotMaskMissingApi(): void
    {
        $statuses = [];
        foreach ([['ROLE_ADMIN'], ['ROLE_DIRECTOR'], ['ROLE_LAWYER'], ['ROLE_TECHNICIAN']] as $roles) { $statuses[] = $this->call('GET', '/api/dashboard/legal/contracts', $this->user($roles)); }
        $statuses[] = $this->call('GET', '/contracts/1', null);
        $assetsFromRoot = str_contains($this->client->getResponse()->getContent(), 'src="/assets/');
        $statuses[] = $this->call('GET', '/api/not-a-route', $this->user(['ROLE_ADMIN']));
        self::assertSame([[200, 200, 200, 403, 200, 404], true], [$statuses, $assetsFromRoot]);
    }

    private function supplierData(): array
    {
        $owner = new LandLord('Owner');
        $object = new RentalObject($owner, '100', 'Supplier object', 'Address');
        $provider = new \App\Entity\UnityProvider('Supplier');
        $water = new Unity('Water', true);
        $power = new Unity('Power', true);
        foreach ([$owner, $object, $provider, $water, $power] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        return ['objectId' => $object->getId(), 'unityProviderId' => $provider->getId(), 'expiryDate' => '2027-03-31', 'unityIds' => [$water->getId(), $power->getId()]];
    }

    public function testSupplierContractsKeepPreviousRecordsAndSupportMultipleUtilitiesAndSuspension(): void
    {
        $body = $this->supplierData();
        $lawyer = $this->user(['ROLE_LAWYER']);
        $created = $this->call('POST', '/api/unity-provider-contracts', $lawyer, $body);
        $first = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $updated = $this->call('PUT', '/api/unity-provider-contracts/'.$first['id'], $lawyer, [...$body, 'isSuspended' => true]);
        $state = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $second = $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'expiryDate' => '2028-03-31']);
        $this->call('GET', '/api/objects/'.$body['objectId'].'/unity-provider-contracts', $lawyer);
        $list = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $history = $this->em->getConnection()->fetchOne('SELECT is_suspended FROM unity_provider_contracts_history WHERE id = ?', [$first['id']]);

        self::assertSame([201, 200, 201, 2, 2, 'suspended', false], [$created, $updated, $second, $list['total'], count($state['unities']), $state['status'], $history]);
    }

    public function testSupplierContractsAreFilteredByBothObjectAndProvider(): void
    {
        $first = $this->supplierData();
        $other = $this->supplierData();
        $lawyer = $this->user(['ROLE_LAWYER']);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, $first);
        $created = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$first, 'unityProviderId' => $other['unityProviderId']]);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$first, 'objectId' => $other['objectId']]);
        $this->call('GET', '/api/objects/'.$first['objectId'].'/unity-provider-contracts?unityProviderId='.$first['unityProviderId'], $lawyer);
        $list = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $invalid = $this->call('GET', '/api/objects/'.$first['objectId'].'/unity-provider-contracts?unityProviderId=-1', $lawyer);

        self::assertSame([1, [$created['id']], 422], [$list['total'], array_column($list['items'], 'id'), $invalid]);
    }

    public function testSupplierContractValidationAndWritePermissions(): void
    {
        $body = $this->supplierData();
        $statuses = [];
        foreach ([['ROLE_DIRECTOR'], ['ROLE_TECHNICIAN']] as $roles) { $statuses[] = $this->call('POST', '/api/unity-provider-contracts', $this->user($roles), $body); }
        $lawyer = $this->user(['ROLE_LAWYER']);
        foreach ([['expiryDate' => ''], ['contractDate' => '2028-01-01'], ['unityIds' => [2147483647]]] as $change) { $statuses[] = $this->call('POST', '/api/unity-provider-contracts', $lawyer, array_replace($body, $change)); }
        self::assertSame([403, 403, 422, 422, 422], $statuses);
    }

    public function testSupplierContractExpiryUsesConfiguredDaysAndDoesNotDuplicateSuspendedContracts(): void
    {
        $body = $this->supplierData();
        $lawyer = $this->user(['ROLE_LAWYER']);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'isSuspended' => true]);
        $generator = static::getContainer()->get(\App\Service\UnityProviderContractNotificationService::class);
        $early = $generator->generate(new \DateTimeImmutable('2027-02-28'));
        $due = $generator->generate(new \DateTimeImmutable('2027-03-01'));
        $again = $generator->generate(new \DateTimeImmutable('2027-03-01'));
        $this->em->getConnection()->executeStatement('UPDATE notification_types SET is_active = false WHERE id = 4');
        $this->user(['ROLE_LAWYER']);
        $off = $generator->generate(new \DateTimeImmutable('2027-03-01'));
        $rows = $this->em->getConnection()->fetchAllAssociative("SELECT kind, due_date FROM notifications WHERE kind = 'supplier_contract.expiry'");
        self::assertSame([0, 1, 0, 0, [['kind' => 'supplier_contract.expiry', 'due_date' => '2027-03-31']]], [$early, $due, $again, $off, $rows]);
    }

    public function testSupplierEquipmentAndArchivedMembershipMatchCoveredUtilities(): void
    {
        $body = $this->supplierData();
        $object = $this->em->find(RentalObject::class, $body['objectId']);
        $provider = $this->em->find(\App\Entity\UnityProvider::class, $body['unityProviderId']);
        $meters = [];
        foreach ($body['unityIds'] as $unityId) {
            $unity = $this->em->find(Unity::class, $unityId);
            $link = new \App\Entity\ObjectUnityProvider($provider, $object, $unity);
            $meter = new Meter($unity, new \DateTimeImmutable('2020-01-01'), 0, 365, 'Meter-'.$unityId, new \DateTimeImmutable('2026-11-22'));
            foreach ([$link, $meter, new ObjectMeter($object, $meter, null, $link)] as $entity) { $this->em->persist($entity); }
            $meters[] = $meter;
        }
        $this->em->flush();
        $expectedMeter = $meters[0]->getId();
        $lawyer = $this->user(['ROLE_LAWYER']);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, $body);
        $first = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->call('PUT', '/api/unity-provider-contracts/'.$first['id'], $lawyer, [...$body, 'unityIds' => [$body['unityIds'][0]]]);
        $second = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $old = json_decode($this->em->getConnection()->fetchOne('SELECT unity_ids FROM unity_provider_contracts_history WHERE id = ? ORDER BY valid_to DESC LIMIT 1', [$first['id']]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([2, [$expectedMeter], ['2027-11-22'], $body['unityIds']], [count($first['meters']), array_column($second['meters'], 'id'), array_column($second['meters'], 'nextVerificationDate'), $old]);
    }

    public function testSupplierDashboardFiltersCombineBeforePagination(): void
    {
        $body = $this->supplierData();
        $lawyer = $this->user(['ROLE_LAWYER']);
        $object = $this->em->find(RentalObject::class, $body['objectId']);
        $provider = $this->em->find(\App\Entity\UnityProvider::class, $body['unityProviderId']);
        $water = $this->em->find(Unity::class, $body['unityIds'][0]);
        $link = new \App\Entity\ObjectUnityProvider($provider, $object, $water);
        $meter = new Meter($water, new \DateTimeImmutable('2020-01-01'), 0, 365);
        foreach ([$link, $meter, new ObjectMeter($object, $meter, null, $link)] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'unityIds' => [$body['unityIds'][0]], 'expiryDate' => '2099-01-01']);
        $created = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'isSuspended' => true, 'expiryDate' => '2099-01-01']);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'unityIds' => [$body['unityIds'][1]], 'expiryDate' => '2099-01-01']);
        $this->call('POST', '/api/unity-provider-contracts', $lawyer, [...$body, 'expiryDate' => '2000-01-01']);
        $base = '/api/unity-provider-contracts?unityProviderId='.$body['unityProviderId'];
        $this->call('GET', $base.'&status=active&unityId='.$water->getId().'&withMeters=1', $lawyer);
        $matching = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->call('GET', $base.'&status=active&unityId='.$body['unityIds'][1].'&withMeters=1', $lawyer);
        $withoutMeter = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->call('GET', $base.'&status=finished', $lawyer);
        $finished = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $invalid = $this->call('GET', $base.'&status=unknown', $lawyer);

        self::assertSame([1, [$created['id']], 0, 1, 422], [$matching['total'], array_column($matching['items'], 'id'), $withoutMeter['total'], $finished['total'], $invalid]);
    }
}
