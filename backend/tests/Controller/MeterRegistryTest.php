<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\LandLord;
use App\Entity\Meter;
use App\Entity\RentalObject;
use App\Entity\Space;
use App\Entity\Unity;
use App\Entity\User;
use App\Service\MeterScheduleService;
use App\Service\MeterVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MeterRegistryTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private int $objectId;
    private int $spaceId;
    private int $unityId;

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
        $owner = new LandLord('Meter owner');
        $object = new RentalObject($owner, '100', 'Meter object', 'Address');
        $space = new Space($object, 'Room', '10');
        $unity = new Unity('Electricity', true, 'kWh');

        foreach ([$owner, $object, $space, $unity] as $record) {
            $this->em->persist($record);
        }

        $this->em->flush();
        $this->objectId = $object->getId();
        $this->spaceId = $space->getId();
        $this->unityId = $unity->getId();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function token(array $roles): string
    {
        $user = new User(bin2hex(random_bytes(8)).'@meter.test');
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

        return 204 === $this->client->getResponse()->getStatusCode() ? [] : json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function input(array $changes = []): array
    {
        return array_replace(['utilityId' => $this->unityId, 'createDate' => '2020-01-01', 'startRead' => 10, 'verificationIntervalDays' => 365, 'objectId' => $this->objectId], $changes);
    }

    private function create(array $changes = []): array
    {
        $record = $this->call('POST', '/api/meters', $this->input($changes));
        self::assertResponseStatusCodeSame(201);

        return $record;
    }

    public function testModelAndCommentSurviveDetachmentAndCanBeCleared(): void
    {
        $meter = $this->create(['model' => '  Model 123  ', 'comment' => '  Device note  ']);
        $detached = $this->call('PUT', '/api/meters/'.$meter['id'].'/installation', []);
        $updated = $this->call('PUT', '/api/meters/'.$meter['id'], $this->input(['objectId' => null, 'model' => ' ', 'comment' => '']));

        self::assertSame(
            ['Model 123', 'Device note', 'Model 123', 'Device note', null, null],
            [$meter['model'], $meter['comment'], $detached['model'], $detached['comment'], $updated['model'], $updated['comment']]
        );
    }

    public function testCreateUninstalledMeterAndAssignItLater(): void
    {
        $meter = $this->create(['objectId' => null]);
        $free = $this->call('GET', '/api/meters?state=uninstalled&utilityId='.$this->unityId);
        $installed = $this->call('PUT', '/api/meters/'.$meter['id'].'/installation', ['objectId' => $this->objectId]);
        $after = $this->call('GET', '/api/meters?state=uninstalled&utilityId='.$this->unityId);

        self::assertSame(
            [null, false, [$meter['id']], $this->objectId, 0],
            [$meter['installation'], $meter['readingRequired'], array_column($free['items'], 'id'), $installed['installation']['id'], $after['total']]
        );
    }

    public function testSelectingAFreeMeterRejectsAnAlreadyInstalledMeter(): void
    {
        $meter = $this->create();
        $result = $this->call('PUT', '/api/meters/'.$meter['id'].'/installation', ['spaceId' => $this->spaceId, 'utilityId' => $this->unityId, 'requireUninstalled' => true]);
        $status = $this->client->getResponse()->getStatusCode();
        $unchanged = $this->call('GET', '/api/meters/'.$meter['id']);

        self::assertSame([422, true, 'object'], [$status, isset($result['errors']['meterId']), $unchanged['installation']['type']]);
    }

    public function testCreateMoveDetachAndReinstallPreserveReadingAndWarning(): void
    {
        $meter = $this->create(['serialNumber' => '0001']);
        $base = '/api/meters/'.$meter['id'];
        $this->call('POST', $base.'/readings', ['meterReadingDate' => '2026-01-01T12:00:00', 'meterRead' => 50]);
        $moved = $this->call('PUT', $base.'/installation', ['spaceId' => $this->spaceId, 'comment' => 'Room']);
        $objectList = $this->call('GET', '/api/meters?objectId='.$this->objectId);
        $detached = $this->call('PUT', $base.'/installation', []);
        $uninstalled = $this->call('GET', '/api/meters?state=uninstalled');
        $reinstalled = $this->call('PUT', $base.'/installation', ['objectId' => $this->objectId]);
        $this->em->clear();
        $saved = $this->call('GET', $base);

        self::assertSame(
            ['0001', true, 25, 'space', $this->spaceId, 1, null, false, 1, 'object', 50, true],
            [$meter['serialNumber'], $meter['verificationExpired'], $meter['effectiveReadingDay'], $moved['installation']['type'], $moved['installation']['id'],
                $objectList['total'], $detached['installation'], $detached['readingRequired'], $uninstalled['total'], $reinstalled['installation']['type'],
                $saved['lastReading']['meterRead'], $saved['verificationExpired']]
        );
    }

    public function testObjectAndSpaceInstallationsExposeCurrentLandLordColour(): void
    {
        $common = $this->create();
        $room = $this->create(['objectId' => null, 'spaceId' => $this->spaceId]);
        $owner = $this->em->find(RentalObject::class, $this->objectId)->getLandLord();
        $this->call('PUT', '/api/land-lords/'.$owner->getId(), ['name' => $owner->getName(), 'color' => '#d63939']);
        $colouredCommon = $this->call('GET', '/api/meters/'.$common['id']);
        $colouredRoom = $this->call('GET', '/api/meters/'.$room['id']);

        self::assertSame(
            [null, '#d63939', '#d63939'],
            [$common['installation']['object']['landLord']['color'] ?? null, $colouredCommon['installation']['object']['landLord']['color'] ?? null, $colouredRoom['installation']['object']['landLord']['color'] ?? null],
        );
    }

    public function testObjectSpaceAndUtilityFiltersAreCombinedOnTheServer(): void
    {
        $water = new Unity('Water', true, 'm3');
        $other = new RentalObject($this->em->find(RentalObject::class, $this->objectId)->getLandLord(), '100', 'Other object', 'Address');
        $this->em->persist($water);
        $this->em->persist($other);
        $this->em->flush();
        $common = $this->create();
        $room = $this->create(['objectId' => null, 'spaceId' => $this->spaceId]);
        $this->create(['utilityId' => $water->getId()]);
        $this->create(['objectId' => $other->getId()]);
        $detached = $this->create();
        $this->call('PUT', '/api/meters/'.$detached['id'].'/installation', []);
        $object = $this->call('GET', '/api/meters?objectId='.$this->objectId.'&utilityId='.$this->unityId);
        $space = $this->call('GET', '/api/meters?spaceId='.$this->spaceId.'&utilityId='.$this->unityId);
        $onlyObject = $this->call('GET', '/api/meters?objectId='.$this->objectId.'&utilityId='.$this->unityId.'&objectOnly=1');
        $empty = $this->call('GET', '/api/meters?objectId='.$this->objectId.'&spaceId='.$this->spaceId.'&utilityId='.$water->getId());
        $installed = $this->call('GET', '/api/meters?utilityId='.$this->unityId.'&state=installed');
        $uninstalled = $this->call('GET', '/api/meters?utilityId='.$water->getId().'&state=uninstalled');

        self::assertSame(
            [[$room['id'], $common['id']], [$room['id']], 0, 3, 0, [$common['id']]],
            [array_column($object['items'], 'id'), array_column($space['items'], 'id'), $empty['total'], $installed['total'], $uninstalled['total'], array_column($onlyObject['items'], 'id')]
        );
    }

    public function testReadingSubmissionStatusUsesTakenDateAndClampedDeadline(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-11-25T23:59:59', 'meterRead' => 100]);
        $this->call('POST', $base, ['meterReadingDate' => '2026-11-26T00:00:00', 'meterRead' => 110]);
        $rows = $this->call('GET', $base)['items'];
        $custom = $this->create(['readingDay' => 31]);
        $base = '/api/meters/'.$custom['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-02-28T23:59:59', 'meterRead' => 20]);
        $short = $this->call('GET', $base)['items'];
        $override = $this->create(['readingDay' => 15]);
        $base = '/api/meters/'.$override['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-11-16T00:00:00', 'meterRead' => 20]);
        $late = $this->call('GET', $base)['items'];

        self::assertSame(
            [['late', 'on_time'], ['2026-11-25', '2026-11-25'], ['on_time'], ['2026-02-28'], ['late']],
            [array_column($rows, 'submissionStatus'), array_column($rows, 'readingDueDate'), array_column($short, 'submissionStatus'), array_column($short, 'readingDueDate'), array_column($late, 'submissionStatus')]
        );
    }

    public function testPreviousReadingPreviewUsesSelectedDateAndInitialValue(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-01-10T00:00:00', 'meterRead' => 100]);
        $this->call('POST', $base, ['meterReadingDate' => '2026-02-10T00:00:00', 'meterRead' => 200]);
        $initial = $this->call('GET', $base.'/previous?date=2020-01-02');
        $between = $this->call('GET', $base.'/previous?date=2026-02-05');
        $same = $this->call('GET', $base.'/previous?date=2026-02-10');
        $this->call('GET', $base.'/previous?date=2019-01-01');

        self::assertSame([10, 100, 100, 422], [$initial['meterRead'] ?? null, $between['meterRead'] ?? null, $same['meterRead'] ?? null, $this->client->getResponse()->getStatusCode()]);
    }

    public function testPreviousPreviewIncludesLatestSubmissionInSelectedMonth(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        foreach (['2026-01-31', '2026-02-10', '2026-02-25', '2026-03-01'] as $index => $date) {
            $this->call('POST', $base, ['meterReadingDate' => $date.'T12:00:00', 'meterRead' => ($index + 1) * 100]);
        }
        $dates = [];
        foreach (['2026-01-01', '2026-02-01', '2026-02-28', '2026-03-31', '2026-04-01'] as $date) {
            $result = $this->call('GET', $base.'/previous?date='.$date);
            $dates[] = $result['monthReadingDate'] ?? null;
        }

        self::assertSame(['2026-01-31', '2026-02-25', '2026-02-25', '2026-03-01', null], $dates);
    }

    public function testBackdatedReadingCorrectionAndConsumptionUseActualNeighbours(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-01-01T00:00:00', 'meterRead' => 100]);
        $this->call('POST', $base, ['meterReadingDate' => '2026-03-01T00:00:00', 'meterRead' => 300]);
        $middle = $this->call('POST', $base, ['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 200]);
        $this->call('PUT', $base.'/'.$middle['id'], ['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 180]);
        $list = $this->call('GET', $base);

        self::assertSame([[120, 80, 90], '2026-02-01T00:00:00', 3, [180, 100, 10]], [array_column($list['items'], 'consumption'), $list['items'][0]['intervalFrom'], $list['total'], array_column($list['items'], 'previousRead')]);
    }

    #[DataProvider('invalidReadings')]
    public function testInvalidReadingIsRejected(array $input, string $field): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-01-01T00:00:00', 'meterRead' => 100]);
        $this->call('POST', $base, ['meterReadingDate' => '2026-03-01T00:00:00', 'meterRead' => 300]);
        $result = $this->call('POST', $base, $input);

        self::assertSame([422, true], [$this->client->getResponse()->getStatusCode(), isset($result['errors'][$field])]);
    }

    public static function invalidReadings(): iterable
    {
        yield 'decrease' => [['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 90], 'meterRead'];
        yield 'exceeds next' => [['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 310], 'meterRead'];
        yield 'duplicate' => [['meterReadingDate' => '2026-01-01T00:00:00', 'meterRead' => 100], 'meterReadingDate'];
        yield 'before production' => [['meterReadingDate' => '2019-12-31T00:00:00', 'meterRead' => 10], 'meterReadingDate'];
        yield 'fraction' => [['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 150.5], 'meterRead'];
    }

    public function testVerificationHistoryUsesLatestAndClearsExpiredWarning(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'];
        $today = date('Y-m-d');
        $older = date('Y-m-d', strtotime('-10 days'));
        $this->call('POST', $base.'/verifications', ['verificationDate' => $today]);
        $this->call('POST', $base.'/verifications', ['verificationDate' => $older]);
        $this->call('POST', $base.'/verifications', ['verificationDate' => $today]);
        $current = $this->call('GET', $base);
        $history = $this->call('GET', $base.'/verifications');

        self::assertSame([$today, false, [$today, $older]], [$current['verificationDate'], $current['verificationExpired'], array_column($history['items'], 'verificationDate')]);
    }

    public function testScheduleInheritsAndClampsShortMonthAndDetachedMetersHaveNoEvents(): void
    {
        $meter = $this->create(['readingDay' => 31]);
        $schedule = static::getContainer()->get(MeterScheduleService::class);
        $date = $schedule->deadline(new \DateTimeImmutable('2027-02-01'), 31)->format('Y-m-d');
        $before = $this->call('GET', '/api/calendar/meters?start=2027-02-01&end=2027-03-01');
        $this->call('PUT', '/api/meters/'.$meter['id'].'/installation', []);
        $after = $this->call('GET', '/api/calendar/meters?start=2027-02-01&end=2027-03-01');

        self::assertSame(['2027-02-28', '2027-02-28', []], [$date, $before['items'][0]['start'], $after['items']]);
    }

    public function testVerificationDeadlineIsStillValidOnDueDate(): void
    {
        $meter = $this->create();
        $service = static::getContainer()->get(MeterVerificationService::class);
        $entity = $this->em->find(Meter::class, $meter['id']);
        $due = new \DateTimeImmutable($meter['nextVerificationDate']);

        self::assertSame([false, true], [$service->state($entity, $due)['verificationExpired'], $service->state($entity, $due->modify('+1 day'))['verificationExpired']]);
    }

    public function testProviderAssociationIsIdempotentAndDeletingLinkKeepsProvider(): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Supplier']);
        $base = '/api/objects/'.$this->objectId.'/unity-providers';
        $first = $this->call('POST', $base, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $again = $this->call('POST', $base, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $this->call('DELETE', $base.'/'.$first['id']);
        $list = $this->call('GET', $base);
        $saved = $this->call('GET', '/api/unity-providers/'.$provider['id']);

        self::assertSame([$first['id'], [], 'Supplier'], [$again['id'], $list['items'], $saved['name']]);
    }

    public function testTechnicianCanWriteReadingsButViewerCannot(): void
    {
        $meter = $this->create();
        $technician = $this->token(['ROLE_TECHNICIAN']);
        $viewer = $this->token(['ROLE_DIRECTOR']);
        $base = '/api/meters/'.$meter['id'].'/readings';
        $this->call('POST', $base, ['meterReadingDate' => '2026-01-01T00:00:00', 'meterRead' => 100], $technician);
        $allowed = $this->client->getResponse()->getStatusCode();
        $this->call('POST', $base, ['meterReadingDate' => '2026-02-01T00:00:00', 'meterRead' => 200], $viewer);

        self::assertSame([201, 403], [$allowed, $this->client->getResponse()->getStatusCode()]);
    }

    public function testCreationAndInitialLocationAreAtomic(): void
    {
        $before = $this->em->getRepository(Meter::class)->count([]);
        $result = $this->call('POST', '/api/meters', $this->input(['objectId' => 2147483647]));
        $after = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM meters');

        self::assertSame([422, true, $before], [$this->client->getResponse()->getStatusCode(), isset($result['errors']['objectId']), (int) $after]);
    }

    #[DataProvider('meterRoles')]
    public function testRoleMatrixCoversMeterModuleAndCatalogues(array $roles, bool $canEdit, bool $canReadings, bool $canInstall): void
    {
        $meter = $this->create();
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Matrix supplier']);
        $links = '/api/objects/'.$this->objectId.'/unity-providers';
        $link = $this->call('POST', $links, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $token = $this->token($roles);
        $base = '/api/meters/'.$meter['id'];
        $reading = $this->call('POST', $base.'/readings', ['meterReadingDate' => '2025-01-01T00:00:00', 'meterRead' => 50]);
        $statuses = [];

        foreach (['/api/meters', $base, '/api/unities', '/api/unities/'.$this->unityId, '/api/unity-providers', '/api/unity-providers/'.$provider['id'], $links, $base.'/readings', $base.'/verifications'] as $path) {
            $this->call('GET', $path, token: $token);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        $writes = [
            ['POST', '/api/unities', ['name' => 'New utility', 'useMeter' => true], 201],
            ['PUT', '/api/unities/'.$this->unityId, ['name' => 'Updated utility', 'useMeter' => true], 200],
            ['POST', '/api/unity-providers', ['name' => 'New supplier'], 201],
            ['PUT', '/api/unity-providers/'.$provider['id'], ['name' => 'Updated supplier'], 200],
            ['POST', '/api/meters', $this->input(), 201],
            ['PUT', $base, $this->input(['serialNumber' => 'Updated']), 200],
            ['PUT', $base.'/installation', ['spaceId' => $this->spaceId], 200],
            ['POST', $base.'/readings', ['meterReadingDate' => '2026-01-01T00:00:00', 'meterRead' => 100], 201],
            ['PUT', $base.'/readings/'.$reading['id'], ['meterReadingDate' => '2025-01-01T00:00:00', 'meterRead' => 60], 200],
            ['POST', $base.'/verifications', ['verificationDate' => date('Y-m-d')], 201],
            ['POST', $links, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId], 201],
            ['DELETE', $links.'/'.$link['id'], null, 204],
        ];

        foreach ($writes as [$method, $path, $body]) {
            $this->call($method, $path, $body, $token);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        self::assertSame(
            [...array_fill(0, 9, 200), ...array_map(static fn (array $write): int => (str_contains($write[1], '/readings') ? $canReadings : (str_ends_with($write[1], '/installation') || ('POST' === $write[0] && '/api/meters' === $write[1]) ? $canInstall : $canEdit)) ? $write[3] : 403, $writes)],
            $statuses
        );
    }

    public static function meterRoles(): iterable
    {
        yield 'administrator' => [['ROLE_ADMIN'], true, true, true];
        yield 'superadministrator' => [['ROLE_SUPER_ADMIN'], true, true, true];
        yield 'technician' => [['ROLE_TECHNICIAN'], false, true, true];
        yield 'combined roles' => [['ROLE_ACCOUNTANT', 'ROLE_TECHNICIAN'], false, true, true];
        yield 'admin technician' => [['ROLE_ADMIN', 'ROLE_TECHNICIAN'], true, true, true];
        yield 'lawyer technician' => [['ROLE_LAWYER', 'ROLE_TECHNICIAN'], false, true, true];
        yield 'lawyer' => [['ROLE_LAWYER'], false, false, false];
        yield 'accountant' => [['ROLE_ACCOUNTANT'], false, false, false];
        yield 'director' => [['ROLE_DIRECTOR'], false, false, false];
        yield 'authenticated without business roles' => [[], false, false, false];
    }

    #[DataProvider('readingDeadlines')]
    public function testSubmissionTimelinessUsesMeasurementDateOnly(?string $date, string $today, bool $submitted, bool $late, bool $overdue): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'];

        if (null !== $date) {
            $this->call('POST', $base.'/readings', ['meterReadingDate' => $date, 'meterRead' => 100]);
        }

        $service = static::getContainer()->get(MeterScheduleService::class);
        $state = $service->state($this->em->find(Meter::class, $meter['id']), $meter['installation'], new \DateTimeImmutable($today));

        self::assertSame(
            [$submitted, $late, $overdue, !$submitted],
            [$state['readingSubmitted'], $state['readingSubmittedLate'] ?? null, $state['readingOverdue'], $state['readingRequired']]
        );
    }

    public static function readingDeadlines(): iterable
    {
        yield 'backdated before deadline' => ['2026-11-23T12:00:00', '2026-11-30', true, false, false];
        yield 'last second of deadline' => ['2026-11-25T23:59:59', '2026-11-30', true, false, false];
        yield 'after deadline' => ['2026-11-26T00:00:00', '2026-11-30', true, true, false];
        yield 'still expected on deadline' => [null, '2026-11-25', false, false, false];
        yield 'missing after deadline' => [null, '2026-11-26', false, false, true];
    }

    public function testCalendarRetainsLateFlagAndCorrectionRecalculatesIt(): void
    {
        $meter = $this->create();
        $base = '/api/meters/'.$meter['id'].'/readings';
        $reading = $this->call('POST', $base, ['meterReadingDate' => '2026-10-26T12:00:00', 'meterRead' => 100]);
        $calendar = $this->call('GET', '/api/calendar/meters?start=2026-10-01&end=2026-11-01');
        $this->call('PUT', $base.'/'.$reading['id'], ['meterReadingDate' => '2026-10-23T12:00:00', 'meterRead' => 100]);
        $corrected = $this->call('GET', '/api/calendar/meters?start=2026-10-01&end=2026-11-01');

        self::assertSame(
            ['#d63939', true, '#2fb344'],
            [$calendar['items'][0]['color'], str_contains($calendar['items'][0]['title'], 'с просрочкой'), $corrected['items'][0]['color']]
        );
    }

    public function testObjectAcceptsSeveralProvidersOfOneUtilityWithoutAnyMeters(): void
    {
        $first = $this->call('POST', '/api/unity-providers', ['name' => 'First supplier']);
        $second = $this->call('POST', '/api/unity-providers', ['name' => 'Second supplier']);
        $base = '/api/objects/'.$this->objectId.'/unity-providers';
        $this->call('POST', $base, ['unityProviderId' => $first['id'], 'unityId' => $this->unityId]);
        $this->call('POST', $base, ['unityProviderId' => $second['id'], 'unityId' => $this->unityId]);
        $list = $this->call('GET', $base);

        self::assertSame(
            [0, [$second['id'], $first['id']]],
            [$this->em->getRepository(Meter::class)->count([]), array_column(array_column($list['items'], 'unityProvider'), 'id')]
        );
    }

    public function testGlobalSupplierHasIndependentUtilityListsOnDifferentObjects(): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Shared supplier']);
        $water = new Unity('Water', false);
        $other = new RentalObject($this->em->find(RentalObject::class, $this->objectId)->getLandLord(), '100', 'Other object', 'Address');
        $this->em->persist($water);
        $this->em->persist($other);
        $this->em->flush();
        $base = '/api/objects/'.$this->objectId.'/unity-providers';
        $electricity = $this->call('POST', $base, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $this->call('POST', $base, ['unityProviderId' => $provider['id'], 'unityId' => $water->getId()]);
        $this->call('POST', '/api/objects/'.$other->getId().'/unity-providers', ['unityProviderId' => $provider['id'], 'unityId' => $water->getId()]);
        $first = $this->call('GET', $base);
        $second = $this->call('GET', '/api/objects/'.$other->getId().'/unity-providers');
        $filtered = $this->call('GET', $base.'?unityId='.$this->unityId);
        $global = $this->call('GET', '/api/unity-providers/'.$provider['id']);

        self::assertSame(
            [2, 1, [$water->getId()], [$electricity['id']], ['id' => $provider['id'], 'name' => 'Shared supplier']],
            [$first['total'], $second['total'], array_column(array_column($second['items'], 'unity'), 'id'), array_column($filtered['items'], 'id'), $global]
        );
    }

    public function testOptionalMeterSupplierCanBeSelectedClearedAndRemovedWithInstallation(): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Supplier']);
        $link = $this->call('POST', '/api/objects/'.$this->objectId.'/unity-providers', ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $meter = $this->create(['objectUnityProviderId' => $link['id']]);
        $base = '/api/meters/'.$meter['id'].'/installation';
        $cleared = $this->call('PUT', $base, ['objectId' => $this->objectId]);
        $selected = $this->call('PUT', $base, ['objectId' => $this->objectId, 'objectUnityProviderId' => $link['id']]);
        $space = $this->call('PUT', $base, ['spaceId' => $this->spaceId]);
        $this->call('DELETE', '/api/objects/'.$this->objectId.'/unity-providers/'.$link['id']);
        $status = $this->client->getResponse()->getStatusCode();

        self::assertSame([$link['id'], null, $link['id'], null, 204], [$meter['installation']['provider']['id'], $cleared['installation']['provider'], $selected['installation']['provider']['id'], $space['installation']['provider'], $status]);
    }

    #[DataProvider('invalidSupplierAssignments')]
    public function testMeterRejectsSupplierFromAnotherObjectUtilityOrForSpace(string $case): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Supplier']);
        $objectId = $this->objectId;
        $unityId = $this->unityId;

        if ('object' === $case) {
            $other = new RentalObject($this->em->find(RentalObject::class, $this->objectId)->getLandLord(), '100', 'Other object', 'Address');
            $this->em->persist($other);
            $this->em->flush();
            $objectId = $other->getId();
        }

        if ('utility' === $case) {
            $water = new Unity('Water', true);
            $this->em->persist($water);
            $this->em->flush();
            $unityId = $water->getId();
        }

        $link = $this->call('POST', '/api/objects/'.$objectId.'/unity-providers', ['unityProviderId' => $provider['id'], 'unityId' => $unityId]);
        $input = $this->input(['objectUnityProviderId' => $link['id']]);

        if ('space' === $case) {
            $input['objectId'] = null;
            $input['spaceId'] = $this->spaceId;
        }

        $result = $this->call('POST', '/api/meters', $input);

        self::assertSame([422, true, 0], [$this->client->getResponse()->getStatusCode(), isset($result['errors']['objectUnityProviderId']), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM meters')]);
    }

    public static function invalidSupplierAssignments(): iterable
    {
        yield 'another object' => ['object'];
        yield 'another utility' => ['utility'];
        yield 'space meter' => ['space'];
    }

    public function testAssignedSupplierProtectsLinkDeletionAndUtilityChange(): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Supplier']);
        $path = '/api/objects/'.$this->objectId.'/unity-providers';
        $link = $this->call('POST', $path, ['unityProviderId' => $provider['id'], 'unityId' => $this->unityId]);
        $meter = $this->create(['objectUnityProviderId' => $link['id']]);
        $this->call('DELETE', $path.'/'.$link['id']);
        $deletion = $this->client->getResponse()->getStatusCode();
        $water = new Unity('Water', true);
        $this->em->persist($water);
        $this->em->flush();
        $result = $this->call('PUT', '/api/meters/'.$meter['id'], $this->input(['utilityId' => $water->getId()]));

        self::assertSame([409, 422, true], [$deletion, $this->client->getResponse()->getStatusCode(), isset($result['errors']['utilityId'])]);
    }

    public function testDatabaseRejectsMeterSupplierForAnotherUtility(): void
    {
        $provider = $this->call('POST', '/api/unity-providers', ['name' => 'Supplier']);
        $water = new Unity('Water', true);
        $this->em->persist($water);
        $this->em->flush();
        $link = $this->call('POST', '/api/objects/'.$this->objectId.'/unity-providers', ['unityProviderId' => $provider['id'], 'unityId' => $water->getId()]);
        $meter = $this->create();
        $state = null;

        try {
            $this->em->getConnection()->executeStatement('UPDATE object_meters SET object_unity_provider_id = ? WHERE meter_id = ?', [$link['id'], $meter['id']]);
        } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
            $state = $exception->getSQLState();
        }

        self::assertSame('23514', $state);
    }

    public function testTechnicianCannotChangeObjectOrMeterSchedules(): void
    {
        $meter = $this->create();
        $technician = $this->token(['ROLE_TECHNICIAN']);
        $object = $this->em->find(RentalObject::class, $this->objectId);
        $this->call('PUT', '/api/objects/'.$this->objectId.'/meter-settings', ['readingDay' => 10], $technician);
        $schedule = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/objects/'.$this->objectId, ['name' => 'Changed', 'address' => 'Address', 'square' => '100', 'landLordId' => $object->getLandLord()->getId(), 'readingDay' => 10], $technician);
        $settings = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/meters/'.$meter['id'], $this->input(['readingDay' => 10]), $technician);

        self::assertSame([403, 403, 403], [$schedule, $settings, $this->client->getResponse()->getStatusCode()]);
    }

    public function testTechnicianCanCreateMoveRemoveAndReinstallMeter(): void
    {
        $token = $this->token(['ROLE_TECHNICIAN']);
        $meter = $this->call('POST', '/api/meters', $this->input(), $token);
        self::assertResponseStatusCodeSame(201);
        $base = '/api/meters/'.$meter['id'].'/installation';
        $space = $this->call('PUT', $base, ['spaceId' => $this->spaceId], $token);
        $removed = $this->call('PUT', $base, [], $token);
        $reinstalled = $this->call('PUT', $base, ['objectId' => $this->objectId], $token);

        self::assertSame(
            [null, 'space', null, false, $this->objectId],
            [$meter['readingDay'], $space['installation']['type'], $removed['installation'], $removed['readingRequired'], $reinstalled['installation']['id']]
        );
    }

    public function testTechnicianCannotSetCustomScheduleWhenCreatingMeter(): void
    {
        $this->call('POST', '/api/meters', $this->input(['readingDay' => 10]), $this->token(['ROLE_TECHNICIAN']));

        self::assertSame([403, 0], [$this->client->getResponse()->getStatusCode(), $this->em->getRepository(Meter::class)->count([])]);
    }
}
