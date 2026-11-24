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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class ContractSigningAccrualTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->set('clock', new MockClock('2027-02-10 12:00:00 +03:00'));
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('Separate test database required.');
        }
        $this->em->getConnection()->beginTransaction();
        $user = new User(bin2hex(random_bytes(8)).'@signing-accrual.test');
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

    private function body(): array
    {
        $owner = new LandLord('Accrual owner');
        $object = new RentalObject($owner, '100', 'Accrual object', 'Address');
        $renter = new Renter($this->em->find(Ownership::class, 1), 'Accrual renter');
        foreach ([$owner, $object, $renter] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return ['objectId' => $object->getId(), 'renterId' => $renter->getId(), 'contractTypeId' => 1, 'contractNum' => 'ACCRUAL-1', 'contractDate' => '2026-12-01', 'expiryDate' => '2027-04-30', 'monthlyPayment' => '10000', 'securityPayment' => '0', 'square' => '40', 'isSigned' => false];
    }

    private function call(string $method, string $path, array $body): array
    {
        $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token], content: json_encode($body, JSON_THROW_ON_ERROR));

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testLateSigningRequiresConfirmationAndPostsAllRentalMonthsExactlyOnce(): void
    {
        $body = $this->body();
        $contract = $this->call('POST', '/api/contracts', $body);
        $sign = [...$body, 'square' => null, 'isSigned' => true];
        $preview = $this->call('POST', '/api/contracts/'.$contract['id'].'/signing-preview', $sign);
        $before = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contract_accruals');
        $error = $this->call('PUT', '/api/contracts/'.$contract['id'], $sign);
        $status = $this->client->getResponse()->getStatusCode();
        $stillUnsigned = $this->em->getConnection()->fetchOne('SELECT is_signed FROM contracts WHERE id = ?', [$contract['id']]);
        $signed = $this->call('PUT', '/api/contracts/'.$contract['id'], [...$sign, 'signingConfirmation' => $preview['confirmation'] ?? '']);
        $this->call('PUT', '/api/contracts/'.$contract['id'], [...$sign, 'signingConfirmation' => $preview['confirmation'] ?? '']);
        $repeatStatus = $this->client->getResponse()->getStatusCode();
        $rows = $this->em->getConnection()->fetchAllAssociative('SELECT period_month, amount FROM contract_accruals ORDER BY period_month');

        self::assertSame(
            [3, '30000.00', 0, 422, true, false, true, 409, [['period_month' => '2026-12-01', 'amount' => '10000.00'], ['period_month' => '2027-01-01', 'amount' => '10000.00'], ['period_month' => '2027-02-01', 'amount' => '10000.00']]],
            [$preview['monthCount'] ?? null, $preview['totalAmount'] ?? null, $before, $status, isset($error['errors']['signingConfirmation']), $stillUnsigned, $signed['isSigned'] ?? null, $repeatStatus, $rows],
        );
    }

    public function testChangedAmountInvalidatesConfirmationWithoutSigningOrAccruing(): void
    {
        $body = [...$this->body(), 'isSigned' => true];
        $preview = $this->call('POST', '/api/contracts/signing-preview', $body);
        $error = $this->call('POST', '/api/contracts', [...$body, 'monthlyPayment' => '20000', 'signingConfirmation' => $preview['confirmation'] ?? '']);

        self::assertSame([422, true, 0, 0], [$this->client->getResponse()->getStatusCode(), isset($error['errors']['signingConfirmation']), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contracts'), (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM contract_accruals')]);
    }

    public function testPreviewProratesInclusiveRentalDaysAndClampsAccountingStart(): void
    {
        $body = [...$this->body(), 'isSigned' => true, 'contractDate' => '2026-12-16', 'expiryDate' => '2027-01-15', 'monthlyPayment' => '3100'];
        $partial = $this->call('POST', '/api/contracts/signing-preview', $body);
        $rounded = $this->call('POST', '/api/contracts/signing-preview', [...$body, 'contractDate' => '2026-01-01', 'expiryDate' => '2027-01-31', 'monthlyPayment' => '100.005']);
        $future = $this->call('POST', '/api/contracts/signing-preview', [...$body, 'contractDate' => '2027-03-15', 'expiryDate' => '2027-04-30']);

        self::assertSame([2, '3100.00', ['1600.00', '1500.00'], 2, '200.02', 0, '0.00'], [$partial['monthCount'] ?? null, $partial['totalAmount'] ?? null, array_column($partial['periods'] ?? [], 'amount'), $rounded['monthCount'] ?? null, $rounded['totalAmount'] ?? null, $future['monthCount'] ?? null, $future['totalAmount'] ?? null]);
    }
}
