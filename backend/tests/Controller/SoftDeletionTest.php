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

final class SoftDeletionTest extends WebTestCase
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

        return 204 === $this->client->getResponse()->getStatusCode() ? [] : json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testDeletingDraftRetainsRowsAndFreesItsLastSpace(): void
    {
        $body = $this->input();
        $created = $this->call('POST', '/api/contracts', $body);
        $this->call('DELETE', '/api/contracts/'.$created['id']);
        $status = $this->client->getResponse()->getStatusCode();
        $flags = $this->em->getConnection()->fetchAssociative('SELECT c.is_deleted AS contract_deleted, s.is_deleted AS space_deleted FROM contracts c JOIN spaces s ON s.id=c.space_id WHERE c.id=?', [$created['id']]);
        $spaces = $this->call('GET', '/api/objects/'.$body['objectId'].'/spaces');
        $list = $this->call('GET', '/api/contracts?query=SIGN-1');
        $this->call('GET', '/api/contracts/'.$created['id']);

        self::assertSame([204, ['contract_deleted' => true, 'space_deleted' => true], 0, '100', 0, 404], [$status, $flags, $spaces['total'], $spaces['availableSquare'], $list['total'], $this->client->getResponse()->getStatusCode()]);
    }

    public function testSignedContractIsProtectedAndDeletingSuccessorKeepsSharedSpace(): void
    {
        $body = $this->input();
        $first = $this->call('POST', '/api/contracts', [...$body, 'isSigned' => true]);
        $this->call('DELETE', '/api/contracts/'.$first['id']);
        $protected = $this->client->getResponse()->getStatusCode();
        $next = $this->call('POST', '/api/contracts', [...$body, 'previousContractId' => $first['id'], 'contractDate' => '2027-12-01', 'expiryDate' => '2028-11-30']);
        $this->call('DELETE', '/api/contracts/'.$next['id']);
        $status = $this->client->getResponse()->getStatusCode();
        $flags = $this->em->getConnection()->fetchAllAssociative('SELECT is_deleted FROM contracts ORDER BY id');
        $space = $this->em->getConnection()->fetchOne('SELECT is_deleted FROM spaces WHERE id=?', [$first['space']['id']]);

        self::assertSame([409, 204, [['is_deleted' => false], ['is_deleted' => true]], false], [$protected, $status, $flags, $space]);
    }

    public function testUnusedCatalogueIsMarkedAndBlockedWhenReferenced(): void
    {
        $this->actor->setRoles(['ROLE_ADMIN']);
        $this->em->flush();
        $unit = $this->call('POST', '/api/unities', ['name' => 'Soft delete unit', 'useMeter' => true]);
        $this->call('DELETE', '/api/unities/'.$unit['id']);
        $status = $this->client->getResponse()->getStatusCode();
        $marked = $this->em->getConnection()->fetchOne('SELECT is_deleted FROM unities WHERE id=?', [$unit['id']]);
        $list = $this->call('GET', '/api/unities?query=Soft');
        $this->call('DELETE', '/api/contract-types/1');

        self::assertSame([204, true, 0, 409], [$status, $marked, $list['total'], $this->client->getResponse()->getStatusCode()]);
    }
}
