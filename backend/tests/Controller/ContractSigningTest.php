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

final class ContractSigningTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;
    private User $actor;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) { throw new \LogicException('Test database required.'); }
        $this->em->getConnection()->beginTransaction();
        $user = new User(bin2hex(random_bytes(8)).'@signing.test');
        $this->actor = $user;
        $user->setRoles(['ROLE_LAWYER']);
        $user->setActive(true);
        $this->token = bin2hex(random_bytes(32));
        $this->em->persist($user);
        $this->em->persist(new AccessToken($user, $this->token, new \DateTimeImmutable('+1 hour')));
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function input(): array
    {
        $owner = new LandLord('Signing owner');
        $object = new RentalObject($owner, '100', 'Signing object', 'Address');
        $renter = new Renter($this->em->find(Ownership::class, 1), 'Signing renter');
        foreach ([$owner, $object, $renter] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        return ['objectId' => $object->getId(), 'renterId' => $renter->getId(), 'contractTypeId' => 1, 'contractNum' => 'SIGN-1', 'contractDate' => '2026-12-01', 'expiryDate' => '2027-11-30', 'monthlyPayment' => '50000', 'securityPayment' => '0', 'square' => '40', 'isSigned' => false];
    }

    private function call(string $method, string $path, ?array $body = null): array
    {
        $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testUnsignedEditsDoNotCreateHistory(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $changed = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'square' => null, 'contractNum' => 'DRAFT-2', 'monthlyPayment' => '55000']);
        $history = $this->call('GET', '/api/contracts/'.$created['id'].'/history');
        $archived = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history WHERE id = ?', [$created['id']]);

        self::assertSame([false, false, 'DRAFT-2', 0, 0], [$created['isSigned'] ?? null, $changed['isSigned'] ?? null, $changed['contractNum'], $history['total'], $archived]);
    }

    public function testUnsignedContractReservesAreaBeforeRentStartsWithoutDoubleCountingOnSigning(): void
    {
        $start = new \DateTimeImmutable('first day of +2 months');
        $body = [...$this->input(), 'contractDate' => $start->format('Y-m-d'), 'expiryDate' => $start->modify('+1 year')->format('Y-m-d')];
        $created = $this->call('POST', '/api/contracts', $body);
        $before = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');
        $signed = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'square' => null, 'isSigned' => true]);
        $after = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');

        self::assertSame(
            [false, true, '40', '60', true, 1, '40', '60'],
            [$created['isSigned'], $created['space']['active'], $before['occupiedSquare'], $before['availableSquare'], $signed['isSigned'], $after['total'], $after['occupiedSquare'], $after['availableSquare']],
        );
    }

    public function testDraftCanBeResizedAndMovedWithItsReservationAndLandLord(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $resized = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'square' => '75']);
        $sameObject = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');
        $targetBody = $this->input();
        $target = $this->call('GET', '/api/objects/'.$targetBody['objectId']);
        $moved = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'objectId' => $target['id'], 'square' => '60']);
        $sourceArea = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');
        $targetArea = $this->call('GET', '/api/objects/'.$target['id'].'/spaces');
        $history = $this->call('GET', '/api/contracts/'.$created['id'].'/history');

        self::assertSame(
            ['75', '25', $created['space']['id'], $target['id'], $target['landLord']['id'], '100', '40', 0],
            [$resized['space']['square'] ?? null, $sameObject['availableSquare'], $moved['space']['id'] ?? null, $moved['space']['object']['id'] ?? null, $moved['landLord']['id'] ?? null, $sourceArea['availableSquare'], $targetArea['availableSquare'], $history['total']],
        );
    }

    public function testFailedDraftMoveLeavesBothObjectsAndContractUnchanged(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $targetBody = $this->input();
        $this->call('POST', '/api/contracts', [...$targetBody, 'square' => '60']);
        $error = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'objectId' => $targetBody['objectId'], 'square' => '40.0000000000000000001']);
        $status = $this->client->getResponse()->getStatusCode();
        $unchanged = $this->call('GET', '/api/contracts/'.$created['id']);
        $sourceArea = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');
        $targetArea = $this->call('GET', '/api/objects/'.$targetBody['objectId'].'/spaces');

        self::assertSame(
            [422, true, $body['objectId'], '40', '60', '40', 0],
            [$status, isset($error['errors']['square']), $unchanged['space']['object']['id'], $unchanged['space']['square'], $sourceArea['availableSquare'], $targetArea['availableSquare'], (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history WHERE id = ?', [$created['id']])],
        );
    }

    public function testNewContractCannotOverbookAnObjectAndLeavesNoOrphanSpace(): void
    {
        $body = $this->input();
        $this->call('POST', '/api/contracts', [...$body, 'square' => '80']);
        $error = $this->call('POST', '/api/contracts', [...$body, 'square' => '21']);

        self::assertSame(
            [422, true, 1, 1],
            [$this->client->getResponse()->getStatusCode(), isset($error['errors']['square']), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM spaces'), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts')],
        );
    }

    public function testChangingSharedDraftSpacePreservesSignedContractGeometry(): void
    {
        $body = $this->input();
        $first = $this->call('POST', '/api/contracts', [...$body, 'isSigned' => true]);
        $nextBody = [...$body, 'previousContractId' => $first['id'], 'contractDate' => '2027-12-01', 'expiryDate' => '2028-11-30'];
        $next = $this->call('POST', '/api/contracts', $nextBody);
        $target = $this->input();
        $moved = $this->call('PUT', '/api/contracts/'.$next['id'], [...$nextBody, 'previousContractId' => null, 'objectId' => $target['objectId'], 'square' => '65']);
        $original = $this->call('GET', '/api/contracts/'.$first['id']);

        self::assertSame(
            [$body['objectId'], '40', true, $target['objectId'], '65'],
            [$original['space']['object']['id'], $original['space']['square'], ($moved['space']['id'] ?? null) !== $original['space']['id'], $moved['space']['object']['id'] ?? null, $moved['space']['square'] ?? null],
        );
    }

    public function testObjectCannotBeReducedBelowReservedArea(): void
    {
        $body = $this->input();
        $this->call('POST', '/api/contracts', $body);
        $object = $this->call('GET', '/api/objects/'.$body['objectId']);
        $error = $this->call('PUT', '/api/objects/'.$object['id'], ['name' => $object['name'], 'address' => $object['address'], 'square' => '39', 'landLordId' => $object['landLord']['id']]);
        $status = $this->client->getResponse()->getStatusCode();
        $unchanged = $this->call('GET', '/api/objects/'.$object['id']);

        self::assertSame([422, true, '100'], [$status, isset($error['errors']['square']), $unchanged['square']]);
    }

    public function testAvailabilityReturnsDraftBudgetWithoutIgnoringSharedReservations(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $free = $this->call('GET', '/api/objects/'.$body['objectId'].'/availability');
        $editing = $this->call('GET', '/api/objects/'.$body['objectId'].'/availability?contractId='.$created['id']);
        $next = [...$body, 'previousContractId' => $created['id'], 'contractDate' => '2027-12-01', 'expiryDate' => '2028-11-30'];
        $this->call('POST', '/api/contracts', $next);
        $shared = $this->call('GET', '/api/objects/'.$body['objectId'].'/availability?contractId='.$created['id']);

        self::assertSame(['60', '100', '60'], [$free['availableSquare'] ?? null, $editing['availableSquare'] ?? null, $shared['availableSquare'] ?? null]);
    }

    public function testSignedContractCannotBeMovedAndEmptyDraftAreaIsInvalid(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', [...$body, 'isSigned' => true]);
        $target = $this->input();
        $error = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'objectId' => $target['objectId'], 'isSigned' => true]);
        $status = $this->client->getResponse()->getStatusCode();
        $draft = $this->call('POST', '/api/contracts', $target);
        $empty = $this->call('PUT', '/api/contracts/'.$draft['id'], [...$target, 'square' => '']);

        self::assertSame([422, true, 422, true], [$status, isset($error['errors']['objectId']), $this->client->getResponse()->getStatusCode(), isset($empty['errors']['square'])]);
    }

    public static function signingRoles(): iterable
    {
        yield 'lawyer' => ['ROLE_LAWYER'];
        yield 'administrator' => ['ROLE_ADMIN'];
        yield 'super administrator' => ['ROLE_SUPER_ADMIN'];
    }

    #[DataProvider('signingRoles')]
    public function testSigningStartsHistoryWithoutArchivingUnsignedVersions(string $role): void
    {
        $this->actor->setRoles([$role]);
        $this->em->flush();

        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $signed = $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'square' => null, 'isSigned' => true, 'monthlyPayment' => '55000']);
        $countBefore = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts_history WHERE id = ?', [$created['id']]);
        $this->call('PUT', '/api/contracts/'.$created['id'].'/amendment', ['renterId' => $body['renterId'], 'expiryDate' => $body['expiryDate'], 'monthlyPayment' => '60000']);
        $old = $this->em->getConnection()->fetchFirstColumn('SELECT monthly_payment FROM contracts_history WHERE id = ? ORDER BY valid_to', [$created['id']]);

        self::assertSame([true, 0, ['55000']], [$signed['isSigned'] ?? null, $countBefore, $old]);
    }

    public function testSignedFlagCannotBeRevokedThroughApiOrDirectDatabaseWrite(): void
    {
        $body = [...$this->input(), 'isSigned' => true];
        $created = $this->call('POST', '/api/contracts', $body);
        $this->call('PUT', '/api/contracts/'.$created['id'], [...$body, 'square' => null, 'isSigned' => false]);
        $status = $this->client->getResponse()->getStatusCode();
        $db = $this->em->getConnection();
        $db->createSavepoint('signature_revoke');
        $state = null;
        try { $db->executeStatement('UPDATE contracts SET is_signed = false WHERE id = ?', [$created['id']]); } catch (\Doctrine\DBAL\Exception\DriverException $e) { $state = $e->getSQLState(); } finally { $db->rollbackSavepoint('signature_revoke'); }

        self::assertSame([409, '23514'], [$status, $state]);
    }

    public function testUnsignedContractCannotReceiveAnAccrual(): void
    {
        $created = $this->call('POST', '/api/contracts', $this->input());
        $db = $this->em->getConnection();
        $db->createSavepoint('unsigned_accrual');
        $state = null;
        try { $db->insert('contract_accruals', ['contract_id' => $created['id'], 'period_month' => '2026-12-01', 'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'monthly_payment' => '50000', 'amount' => '50000']); } catch (\Doctrine\DBAL\Exception\DriverException $e) { $state = $e->getSQLState(); } finally { $db->rollbackSavepoint('unsigned_accrual'); }

        self::assertSame('23514', $state);
    }
}
