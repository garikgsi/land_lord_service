<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\Contract;
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

final class ContractRegistryTest extends WebTestCase
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

    public function testObjectSpacesHistoryListsActiveFirstThenDescendingIds(): void
    {
        $input = $this->input(['contractDate' => date('Y-m-d'), 'expiryDate' => date('Y-m-d')]);
        $first = $this->call('POST', '/api/contracts', $input);
        $expired = $this->call('POST', '/api/contracts', array_replace($input, [
            'contractDate' => date('Y-m-d', strtotime('-10 days')),
            'expiryDate' => date('Y-m-d', strtotime('-1 day')),
        ]));
        $cancelled = $this->call('POST', '/api/contracts', $input);
        $this->call('PUT', '/api/contracts/'.$cancelled['id'].'/annulment', [
            'annulmentDate' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $reserved = $this->call('POST', '/api/contracts', array_replace($input, [
            'contractDate' => date('Y-m-d', strtotime('+1 day')),
            'expiryDate' => date('Y-m-d', strtotime('+10 days')),
        ]));
        $list = $this->call('GET', '/api/objects/'.$input['objectId'].'/spaces');

        self::assertSame(
            [
                [$reserved['space']['id'], true, $reserved['space']['name'], '40'],
                [$first['space']['id'], true, $first['space']['name'], '40'],
                [$cancelled['space']['id'], false, $cancelled['space']['name'], '40'],
                [$expired['space']['id'], false, $expired['space']['name'], '40'],
            ],
            array_map(static fn (array $space): array => [$space['id'], $space['active'], $space['name'], $space['square']], $list['items'])
        );
    }

    public function testFirstContractCreatesSpaceWithRenterNameAndExactMoney(): void
    {
        $input = $this->input(['square' => '40.1234567890123456789']);
        $created = $this->call('POST', '/api/contracts', $input);
        $status = $this->client->getResponse()->getStatusCode();
        $this->em->clear();
        $read = $this->call('GET', '/api/contracts/'.$created['id']);
        $spaces = $this->call('GET', '/api/objects/'.$input['objectId'].'/spaces');

        self::assertSame(
            [201, '40.1234567890123456789 кв.м ИП Арендатор', $input['monthlyPayment'], 1, '40.1234567890123456789', '59.8765432109876543211', true],
            [$status, $read['space']['name'], $read['monthlyPayment'], $spaces['total'], $spaces['occupiedSquare'], $spaces['availableSquare'], $read['space']['active']]
        );
    }

    public function testTwoEqualAreasAreBothCountedAndFutureFirstContractReservesImmediately(): void
    {
        $input = $this->input(['contractDate' => date('Y-m-d', strtotime('+2 days'))]);
        $this->call('POST', '/api/contracts', $input);
        $input['contractTypeId'] = 2;
        $this->call('POST', '/api/contracts', $input);
        $spaces = $this->call('GET', '/api/objects/'.$input['objectId'].'/spaces');

        self::assertSame([2, '80', '20'], [$spaces['total'], $spaces['occupiedSquare'], $spaces['availableSquare']]);
    }

    public function testNextContractKeepsSpaceAndDoesNotCountItsAreaTwice(): void
    {
        $input = $this->input();
        $first = $this->call('POST', '/api/contracts', $input);
        $input['previousContractId'] = $first['id'];
        $input['contractDate'] = date('Y-m-d', strtotime('+11 days'));
        $input['expiryDate'] = date('Y-m-d', strtotime('+20 days'));
        unset($input['square']);
        $next = $this->call('POST', '/api/contracts', $input);
        $status = $this->client->getResponse()->getStatusCode();
        $spaces = $this->call('GET', '/api/objects/'.$input['objectId'].'/spaces');

        self::assertSame([201, $first['space']['id'], 1, '40', false], [$status, $next['space']['id'], $spaces['total'], $spaces['occupiedSquare'], $this->call('GET', '/api/contracts/'.$first['id'])['canRecontract']]);
    }

    #[DataProvider('invalidContracts')]
    public function testInvalidContractNeverLeavesAnOrphanSpace(array $changes, string $field): void
    {
        $body = $this->call('POST', '/api/contracts', $this->input($changes));

        self::assertSame([422, true, 0, 0], [$this->client->getResponse()->getStatusCode(), isset($body['errors'][$field]), $this->em->getRepository(Space::class)->count([]), $this->em->getRepository(Contract::class)->count([])]);
    }

    public static function invalidContracts(): iterable
    {
        yield [['contractNum' => ' '], 'contractNum'];
        yield [['monthlyPayment' => '-1'], 'monthlyPayment'];
        yield [['monthlyPayment' => ''], 'monthlyPayment'];
        yield [['securityPayment' => '-1'], 'securityPayment'];
        yield [['square' => null], 'square'];
        yield [['square' => '0'], 'square'];
        yield [['square' => '-1'], 'square'];
        yield [['renterId' => 2147483647], 'renterId'];
        yield [['contractTypeId' => 2147483647], 'contractTypeId'];
        yield [['contractDate' => '2026-02-30'], 'contractDate'];
        yield [['expiryDate' => '2000-01-01'], 'expiryDate'];
        yield [['increaseDate' => '2026-12-01'], 'increaseDate'];
    }

    public function testNoGapAndNoSharedBoundaryForNextContract(): void
    {
        $input = $this->input();
        $first = $this->call('POST', '/api/contracts', $input);
        $input['previousContractId'] = $first['id'];
        $input['contractDate'] = $input['expiryDate'];
        $input['expiryDate'] = date('Y-m-d', strtotime('+20 days'));
        $body = $this->call('POST', '/api/contracts', $input);

        self::assertSame([422, true, 1], [$this->client->getResponse()->getStatusCode(), isset($body['errors']['contractDate']), $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM spaces')]);
    }

    public function testAnnulmentTodayIsInclusiveAndPastAnnulmentFreesArea(): void
    {
        $input = $this->input(['contractDate' => date('Y-m-d', strtotime('-3 days'))]);
        $first = $this->call('POST', '/api/contracts', $input);
        $today = $this->call('PUT', '/api/contracts/'.$first['id'].'/annulment', ['annulmentDate' => date('Y-m-d')]);
        $yesterday = $this->call('PUT', '/api/contracts/'.$first['id'].'/annulment', ['annulmentDate' => date('Y-m-d', strtotime('-1 day'))]);
        $spaces = $this->call('GET', '/api/objects/'.$input['objectId'].'/spaces');

        self::assertSame([true, false, '0', '100'], [$today['space']['active'], $yesterday['space']['active'], $spaces['occupiedSquare'], $spaces['availableSquare']]);
    }

    public function testCancelledBeforeStartNeverOccupiesSpace(): void
    {
        $first = $this->call('POST', '/api/contracts', $this->input(['contractDate' => date('Y-m-d', strtotime('+2 days'))]));
        $cancelled = $this->call('PUT', '/api/contracts/'.$first['id'].'/annulment', ['annulmentDate' => date('Y-m-d')]);

        self::assertSame(['cancelled', false, 1], [$cancelled['status'], $cancelled['space']['active'], $this->em->getRepository(Contract::class)->count([])]);
    }

    public function testExpiredSpaceCannotBeReused(): void
    {
        $input = $this->input(['contractDate' => date('Y-m-d', strtotime('-10 days')), 'expiryDate' => date('Y-m-d', strtotime('-2 days'))]);
        $first = $this->call('POST', '/api/contracts', $input);
        $input['previousContractId'] = $first['id'];
        $input['contractDate'] = date('Y-m-d', strtotime('-1 day'));
        $input['expiryDate'] = date('Y-m-d', strtotime('+2 days'));
        $body = $this->call('POST', '/api/contracts', $input);

        self::assertSame([422, true], [$this->client->getResponse()->getStatusCode(), isset($body['errors']['previousContractId'])]);
    }

    public function testContractEditsPreserveSpaceAndSpaceCanBeRenamed(): void
    {
        $input = $this->input();
        $created = $this->call('POST', '/api/contracts', $input);
        $input['contractNum'] = 'Corrected';
        $input['monthlyPayment'] = '999.1234567890123456789';
        $updated = $this->call('PUT', '/api/contracts/'.$created['id'], $input);
        $renamed = $this->call('PUT', '/api/spaces/'.$created['space']['id'], ['name' => 'Новое название']);

        self::assertSame(['Corrected', $input['monthlyPayment'], $created['space']['id'], 'Новое название', '40'], [$updated['contractNum'], $updated['monthlyPayment'], $updated['space']['id'], $renamed['name'], $renamed['square']]);
    }

    public function testReadOnlyRolesCanReadButCannotCreateOrAnnul(): void
    {
        $input = $this->input();
        $created = $this->call('POST', '/api/contracts', $input);
        $director = $this->token(['ROLE_DIRECTOR']);
        $this->call('GET', '/api/contracts/'.$created['id'], token: $director);
        $read = $this->client->getResponse()->getStatusCode();
        $this->call('POST', '/api/contracts', $input, $director);
        $create = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/contracts/'.$created['id'].'/annulment', ['annulmentDate' => date('Y-m-d')], $director);

        self::assertSame([200, 403, 403], [$read, $create, $this->client->getResponse()->getStatusCode()]);
    }

    public function testRegistryFiltersAndNoPhysicalDeletion(): void
    {
        $input = $this->input();
        $created = $this->call('POST', '/api/contracts', $input);
        $matching = $this->call('GET', '/api/contracts?objectId='.$input['objectId'].'&expiryFrom='.$input['expiryDate'].'&expiryTo='.$input['expiryDate']);
        $empty = $this->call('GET', '/api/contracts?objectId='.$input['objectId'].'&expiryTo=2000-01-01');
        $this->call('DELETE', '/api/contracts/'.$created['id']);

        self::assertSame([1, 0, 409, 1], [$matching['total'], $empty['total'], $this->client->getResponse()->getStatusCode(), $this->em->getRepository(Contract::class)->count([])]);
    }

    #[DataProvider('rentalPeriodFilters')]
    public function testRegistryPeriodIncludesAnyOverlappingRentalDay(?string $from, ?string $to, ?string $annulment, int $expected): void
    {
        $input = $this->input(['contractDate' => '2026-11-01', 'expiryDate' => '2026-12-31']);
        $created = $this->call('POST', '/api/contracts', $input);
        if (null !== $annulment) {
            $this->call('PUT', '/api/contracts/'.$created['id'].'/annulment', ['annulmentDate' => $annulment]);
        }
        $params = array_filter(['objectId' => $input['objectId'], 'expiryFrom' => $from, 'expiryTo' => $to], static fn ($value) => null !== $value);
        $result = $this->call('GET', '/api/contracts?'.http_build_query($params));

        self::assertSame($expected, $result['total']);
    }

    public static function rentalPeriodFilters(): iterable
    {
        yield 'selected period inside contract' => ['2026-11-24', '2026-12-06', null, 1];
        yield 'contract inside selected period' => ['2026-10-01', '2027-01-01', null, 1];
        yield 'overlap at rental start' => ['2026-10-25', '2026-11-05', null, 1];
        yield 'overlap at rental end' => ['2026-12-25', '2027-01-05', null, 1];
        yield 'start day included' => ['2026-11-01', '2026-11-01', null, 1];
        yield 'end day included' => ['2026-12-31', '2026-12-31', null, 1];
        yield 'entirely before contract' => ['2026-10-01', '2026-10-31', null, 0];
        yield 'entirely after contract' => ['2027-01-01', '2027-02-01', null, 0];
        yield 'open beginning' => [null, '2026-11-24', null, 1];
        yield 'open end' => ['2026-11-24', null, null, 1];
        yield 'annulled before selected period' => ['2026-11-24', '2026-12-06', '2026-11-23', 0];
        yield 'annulment day included' => ['2026-11-23', '2026-11-23', '2026-11-23', 1];
        yield 'cancelled before rental start' => ['2026-10-01', '2027-01-01', '2026-10-31', 0];
    }

    public function testRenterCanHaveNoInnButSuppliedInnIsUnique(): void
    {
        $first = $this->call('POST', '/api/renters', ['name' => 'First', 'ownershipId' => 2]);
        $changed = $this->call('PUT', '/api/renters/'.$first['id'], ['name' => 'First', 'ownershipId' => 2, 'inn' => '123456789012']);
        $duplicate = $this->call('POST', '/api/renters', ['name' => 'Second', 'ownershipId' => 2, 'inn' => '123456789012']);

        self::assertSame([null, '123456789012', 422, true], [$first['inn'], $changed['inn'], $this->client->getResponse()->getStatusCode(), isset($duplicate['errors']['inn'])]);
    }

    public function testChangingSpaceAreaThroughContractIsRejected(): void
    {
        $input = $this->input();
        $created = $this->call('POST', '/api/contracts', $input);
        $input['square'] = '99';
        $body = $this->call('PUT', '/api/contracts/'.$created['id'], $input);

        self::assertSame([422, true, '40'], [$this->client->getResponse()->getStatusCode(), isset($body['errors']['square']), $this->em->getConnection()->fetchOne('SELECT square FROM spaces WHERE id = ?', [$created['space']['id']])]);
    }

    public function testAnnulmentCannotCreateGapBeforeAlreadySignedSuccessor(): void
    {
        $input = $this->input();
        $created = $this->call('POST', '/api/contracts', $input);
        $input['previousContractId'] = $created['id'];
        $input['contractDate'] = date('Y-m-d', strtotime('+11 days'));
        $input['expiryDate'] = date('Y-m-d', strtotime('+20 days'));
        $this->call('POST', '/api/contracts', $input);
        $this->call('PUT', '/api/contracts/'.$created['id'].'/annulment', ['annulmentDate' => date('Y-m-d', strtotime('+5 days'))]);

        self::assertSame([409, null], [$this->client->getResponse()->getStatusCode(), $this->em->getConnection()->fetchOne('SELECT annulment_date FROM contracts WHERE id = ?', [$created['id']])]);
    }

    public function testFilesArePrivateDownloadsAndOnlyWritersCanDelete(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input());
        $temporary = tempnam(sys_get_temp_dir(), 'contract-test-');
        file_put_contents($temporary, '<?php echo "not executable";');
        $this->client->request('POST', '/api/contracts/'.$created['id'].'/files', files: [
            'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($temporary, 'договор.php', 'text/plain', test: true),
        ], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        $uploadStatus = $this->client->getResponse()->getStatusCode();
        $file = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $director = $this->token(['ROLE_DIRECTOR']);
        try {
            $this->client->request('GET', '/api/contract-files/'.$file['id'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$director]);
            $type = $this->client->getResponse()->headers->get('Content-Type');
            $attachment = str_starts_with($this->client->getResponse()->headers->get('Content-Disposition'), 'attachment;');
            $this->call('DELETE', '/api/contract-files/'.$file['id'], token: $director);
            $denied = $this->client->getResponse()->getStatusCode();
        } finally {
            $this->client->request('DELETE', '/api/contract-files/'.$file['id'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        }

        self::assertSame([201, 'договор.php', 'application/octet-stream', true, 403, 204], [$uploadStatus, $file['name'], $type, $attachment, $denied, $this->client->getResponse()->getStatusCode()]);
    }

    public function testOversizedFileIsRejected(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input());
        $temporary = tempnam(sys_get_temp_dir(), 'contract-size-test-');
        $stream = fopen($temporary, 'w');
        ftruncate($stream, 20 * 1024 * 1024 + 1);
        fclose($stream);
        try {
            $this->client->request('POST', '/api/contracts/'.$created['id'].'/files', files: [
                'file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($temporary, 'large.bin', test: true),
            ], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        } finally {
            @unlink($temporary);
        }

        self::assertSame([422, 0], [$this->client->getResponse()->getStatusCode(), $this->em->getRepository(\App\Entity\ContractFile::class)->count([])]);
    }
}
