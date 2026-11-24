<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccessToken;
use App\Entity\Contract;
use App\Entity\LandLord;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContractActionsTest extends WebTestCase
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

    public function testCreationAndAmendmentHaveContiguousFullSnapshots(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input(['monthlyPayment' => '100']));
        $initial = $this->call('GET', '/api/contracts/'.$created['id'].'/history');
        $emptyArchive = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history');
        $amended = $this->call('PUT', '/api/contracts/'.$created['id'].'/amendment', [
            'renterId' => $created['renter']['id'], 'expiryDate' => $created['expiryDate'], 'monthlyPayment' => '200',
        ]);
        $history = $this->call('GET', '/api/contracts/'.$created['id'].'/history');
        $old = $this->em->getConnection()->fetchAssociative('SELECT * FROM contracts_history WHERE id = ?', [$created['id']]);

        self::assertSame(
            [1, 'created', 0, '200', 2, 'amendment', true, false, '100', $amended['validFrom'], $created['contractNum'], $created['space']['id']],
            [$initial['total'], $initial['items'][0]['type'], $emptyArchive, $amended['monthlyPayment'], $history['total'],
                $history['items'][0]['type'], $history['items'][0]['current'], $history['items'][1]['current'],
                $old['monthly_payment'], $history['items'][1]['validTo'], $amended['contractNum'], $amended['space']['id']]
        );
    }

    public function testNoOpDoesNotArchiveOrChangeValidity(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input(['monthlyPayment' => '100']));
        $updated = $this->call('PUT', '/api/contracts/'.$created['id'].'/amendment', [
            'renterId' => $created['renter']['id'], 'expiryDate' => $created['expiryDate'], 'monthlyPayment' => '100.00',
        ]);

        self::assertSame(
            [$created['validFrom'], 0],
            [$updated['validFrom'], (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history')]
        );
    }

    public function testFutureIndexationChangesImmediatelyAndVersionsUseSaveTime(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input([
            'contractDate' => '2026-01-01', 'expiryDate' => '2028-12-31', 'monthlyPayment' => '100.005',
        ]));
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $updated = $this->call('POST', '/api/contracts/'.$created['id'].'/indexation', [
            'increaseDate' => '2028-01-31', 'increasePercent' => '10',
            'currentMonthlyPayment' => '100.005', 'newMonthlyPayment' => '110.01',
            'periodic' => true, 'intervalMonths' => 1,
        ]);
        $savedAt = new \DateTimeImmutable($updated['validFrom']);
        $stateBefore = static::getContainer()->get(\App\Service\EntityHistoryService::class)->stateAt(Contract::class, $created['id'], $before);
        $stateAfter = static::getContainer()->get(\App\Service\EntityHistoryService::class)->stateAt(Contract::class, $created['id'], $savedAt);
        $history = $this->call('GET', '/api/contracts/'.$created['id'].'/history');

        self::assertSame(
            ['110.01', '2028-01-31', 1, true, true, '100.005', '110.01', 'indexation', $created['id'], $created['space']['id'], '2028-01-31', null],
            [$updated['monthlyPayment'], $updated['increaseDate'], $updated['increaseIntervalMonths'],
                $savedAt >= $before, $savedAt < new \DateTimeImmutable('2028-01-31'), $stateBefore['monthly_payment'],
                $stateAfter['monthly_payment'], $history['items'][0]['type'], $updated['id'], $updated['space']['id'],
                $history['items'][0]['nextData']['lastIndexationDate'], $history['items'][1]['nextData']['lastIndexationDate']]
        );
    }

    public function testAmountIsAuthoritativeAndPeriodicityDefaultsOff(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input(['monthlyPayment' => '300']));
        $updated = $this->call('POST', '/api/contracts/'.$created['id'].'/indexation', [
            'increaseDate' => $created['contractDate'], 'increasePercent' => '0',
            'currentMonthlyPayment' => '300', 'newMonthlyPayment' => '350', 'calculationMode' => 'amount',
        ]);

        self::assertSame(['350.00', '16.666667', null], [$updated['monthlyPayment'], $updated['increasePercent'], $updated['increaseIntervalMonths']]);
    }

    public function testStaleBaseRejectsIndexationWithoutArchiving(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input(['monthlyPayment' => '100']));
        $this->call('POST', '/api/contracts/'.$created['id'].'/indexation', [
            'increaseDate' => $created['contractDate'], 'increasePercent' => '10',
            'currentMonthlyPayment' => '90', 'newMonthlyPayment' => '99',
        ]);

        self::assertSame([409, 0], [$this->client->getResponse()->getStatusCode(), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history')]);
    }

    public function testTerminationArchivesOnlyPreviousPhysicalState(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input());
        $updated = $this->call('PUT', '/api/contracts/'.$created['id'].'/annulment', ['annulmentDate' => $created['contractDate']]);
        $history = $this->call('GET', '/api/contracts/'.$created['id'].'/history');

        self::assertSame(
            [$created['contractDate'], 'termination', null, $updated['validFrom']],
            [$updated['annulmentDate'], $history['items'][0]['type'], $history['items'][1]['nextData']['annulmentDate'], $history['items'][1]['validTo']]
        );
    }

    public function testReminderUsesOriginalDayWithoutMonthEndDriftAndNeverChangesRent(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input([
            'contractDate' => '2028-01-01', 'expiryDate' => '2028-12-31', 'monthlyPayment' => '100',
        ]));
        $this->call('POST', '/api/contracts/'.$created['id'].'/indexation', [
            'increaseDate' => '2028-01-31', 'increasePercent' => '10', 'currentMonthlyPayment' => '100',
            'newMonthlyPayment' => '110', 'periodic' => true, 'intervalMonths' => 1,
        ]);
        $calendar = $this->call('GET', '/api/calendar/contracts?start=2028-02-01&end=2028-04-01');
        $current = $this->call('GET', '/api/contracts/'.$created['id']);
        $dates = array_column(array_filter($calendar['items'], static fn (array $item): bool => str_starts_with($item['id'], 'reminder-'.$created['id'].'-')), 'start');

        self::assertSame([['2028-02-29', '2028-03-31'], '110.00'], [array_values($dates), $current['monthlyPayment']]);
    }

    public function testDirectorCannotUseWriteActions(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input());
        $director = $this->token(['ROLE_DIRECTOR']);
        $statuses = [];
        foreach (['amendment', 'annulment', 'indexation'] as $action) {
            $this->call('indexation' === $action ? 'POST' : 'PUT', '/api/contracts/'.$created['id'].'/'.$action, [], $director);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        self::assertSame([403, 403, 403], $statuses);
    }

    public function testAllMirrorsMatchSourceColumnsAndRollbackWithEntity(): void
    {
        $input = $this->input();
        $db = $this->em->getConnection();
        $results = [];
        foreach (['land_lords', 'objects', 'renters', 'spaces', 'contracts'] as $table) {
            $columns = $db->fetchFirstColumn('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position', [$table]);
            $archived = $db->fetchFirstColumn('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position', [$table.'_history']);
            $results[] = $columns === array_values(array_diff($archived, ['valid_to']));
        }
        $db->createSavepoint('history_rollback');
        $db->executeStatement("UPDATE renters SET name = 'Changed' WHERE id = ?", [$input['renterId']]);
        $count = (int) $db->fetchOne('SELECT COUNT(*) FROM renters_history');
        $db->rollbackSavepoint('history_rollback');
        $name = $db->fetchOne('SELECT name FROM renters WHERE id = ?', [$input['renterId']]);

        self::assertSame([[true, true, true, true, true], 1, 0, 'ИП Арендатор'], [$results, $count, (int) $db->fetchOne('SELECT COUNT(*) FROM renters_history'), $name]);
    }

    public function testInvalidActionsAreRejectedOnServer(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input(['monthlyPayment' => '100']));
        $statuses = [];
        foreach ([
            ['amendment', ['renterId' => $created['renter']['id'], 'expiryDate' => '2020-01-01', 'monthlyPayment' => '-1']],
            ['indexation', ['increaseDate' => $created['contractDate'], 'increasePercent' => '-101', 'currentMonthlyPayment' => '100', 'newMonthlyPayment' => '0']],
            ['indexation', ['increaseDate' => $created['contractDate'], 'increasePercent' => '1', 'currentMonthlyPayment' => '100', 'newMonthlyPayment' => '101', 'periodic' => true]],
        ] as [$action, $body]) {
            $this->call('amendment' === $action ? 'PUT' : 'POST', '/api/contracts/'.$created['id'].'/'.$action, $body);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        self::assertSame([[422, 422, 422], 0], [$statuses, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history')]);
    }
}
