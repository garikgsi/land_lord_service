<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Dto\ContractInterventionListQuery;
use App\Dto\RegistryListQuery;
use App\Entity\AccessToken;
use App\Entity\Contract;
use App\Entity\ContractType;
use App\Entity\LandLord;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\Space;
use App\Entity\User;
use App\Service\ContractInterventionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContractInterventionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private RentalObject $object;
    private Renter $renter;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('A separate test database is required.');
        }

        $this->em->getConnection()->beginTransaction();
        $owner = new LandLord('Intervention owner');
        $this->object = new RentalObject($owner, '1000', 'Intervention object', 'Address');
        $this->renter = new Renter($this->em->find(Ownership::class, 1), 'Intervention renter');

        foreach ([$owner, $this->object, $this->renter] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function contract(string $end, ?string $increase = null, string $number = 'N-1'): Contract
    {
        $space = new Space($this->object, 'Intervention space '.$number, '10');
        $contract = new Contract($space, $this->renter, $this->object->getLandLord(), $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable($end), new \DateTimeImmutable('2020-01-01'), $number, '100');

        if (null !== $increase) {
            $contract->setIncreaseDate(new \DateTimeImmutable($increase));
            $contract->setIncreasePercent('5');
        }

        $this->em->persist($space);
        $this->em->persist($contract);
        $this->em->flush();

        return $contract;
    }

    public function testIndependentEventsAreGloballySortedIncludingOverdueDates(): void
    {
        $first = $this->contract('2027-04-01', '2027-03-05', 'First');
        $second = $this->contract('2027-03-20', number: 'Second');
        $result = static::getContainer()->get(ContractInterventionService::class)->list(new ContractInterventionListQuery(), new \DateTimeImmutable('2027-03-10'));

        self::assertSame(
            [['2027-03-05', '2027-03-20', '2027-04-01'], ['indexation', 'expiry', 'expiry'], [$first->getId(), $second->getId(), $first->getId()], [true, false, false]],
            [array_column($result['items'], 'interventionDate'), array_column($result['items'], 'reason'), array_column($result['items'], 'contractId'), array_column($result['items'], 'overdue')]
        );
    }

    public function testDatesAreSortedBeforePagination(): void
    {
        for ($day = 23; $day >= 1; --$day) {
            $this->contract(sprintf('2027-05-%02d', $day), number: 'N-'.$day);
        }

        $service = static::getContainer()->get(ContractInterventionService::class);
        $first = $service->list(new ContractInterventionListQuery());
        $second = $service->list(new ContractInterventionListQuery(page: 2));

        self::assertSame(
            [23, 20, '2027-05-01', '2027-05-20', ['2027-05-21', '2027-05-22', '2027-05-23']],
            [$first['total'], count($first['items']), $first['items'][0]['interventionDate'], $first['items'][19]['interventionDate'], array_column($second['items'], 'interventionDate')]
        );
    }

    public function testSameDateKeepsTwoDistinctRowsAndDeadlineDayIsNotOverdue(): void
    {
        $this->contract('2027-05-25', '2027-05-25');
        $result = static::getContainer()->get(ContractInterventionService::class)->list(new ContractInterventionListQuery(), new \DateTimeImmutable('2027-05-25'));

        self::assertSame([2, ['expiry', 'indexation'], [false, false]], [count(array_unique(array_column($result['items'], 'id'))), array_column($result['items'], 'reason'), array_column($result['items'], 'overdue')]);
    }

    public function testSearchCoversContractObjectSpaceAndRenter(): void
    {
        $this->contract('2027-04-01', '2027-03-05', 'Wanted');
        $service = static::getContainer()->get(ContractInterventionService::class);
        $totals = [];

        foreach ([' wanted ', 'intervention object', 'intervention space', 'intervention renter', 'missing'] as $query) {
            $totals[] = $service->list(new ContractInterventionListQuery(query: $query))['total'];
        }

        self::assertSame([2, 2, 2, 2, 0], $totals);
    }

    public function testEarlierTerminationAndCancellationDoNotCreateInvalidDates(): void
    {
        $terminated = $this->contract('2027-04-01', '2027-03-20');
        $terminated->setAnnulmentDate(new \DateTimeImmutable('2027-03-10'));
        $cancelled = $this->contract('2027-06-01');
        $cancelled->setAnnulmentDate(new \DateTimeImmutable('2019-12-01'));
        $this->em->flush();
        $result = static::getContainer()->get(ContractInterventionService::class)->list(new ContractInterventionListQuery());

        self::assertSame(0, $result['total']);
    }

    public function testCompletedIndexationDisappearsButUnperformedDateStaysOverdue(): void
    {
        $contract = $this->contract('2027-12-31', '2027-01-31');
        $service = static::getContainer()->get(ContractInterventionService::class);
        $before = $service->list(new ContractInterventionListQuery(), new \DateTimeImmutable('2027-04-01'));
        $input = new \App\Dto\ContractIndexationInput(increaseDate: '2027-01-31', currentMonthlyPayment: '100', increasePercent: '5');
        static::getContainer()->get(\App\Service\ContractService::class)->index($contract, $input);
        $after = $service->list(new ContractInterventionListQuery());
        $history = $this->em->getConnection()->fetchOne('SELECT last_indexation_date FROM contracts_history WHERE id = ? ORDER BY valid_to DESC LIMIT 1', [$contract->getId()]);

        self::assertSame([['indexation', 'expiry'], ['expiry'], null, '2027-01-31'], [array_column($before['items'], 'reason'), array_column($after['items'], 'reason'), $history, $contract->getLastIndexationDate()?->format('Y-m-d')]);
    }

    public function testPeriodicIndexationKeepsFirstPendingRecurrenceEvenWhenOverdue(): void
    {
        $contract = $this->contract('2027-12-31', '2027-01-31');
        $input = new \App\Dto\ContractIndexationInput(increaseDate: '2027-01-31', currentMonthlyPayment: '100', increasePercent: '5', periodic: true, intervalMonths: 1);
        static::getContainer()->get(\App\Service\ContractService::class)->index($contract, $input);
        $result = static::getContainer()->get(ContractInterventionService::class)->list(new ContractInterventionListQuery(), new \DateTimeImmutable('2027-04-01'));

        self::assertSame(['2027-02-28', true], [$result['items'][0]['interventionDate'], $result['items'][0]['overdue']]);
    }

    public function testNewPlannedDateReappearsAndRecurrenceOutsideLeaseIsExcluded(): void
    {
        $contract = $this->contract('2027-02-15', '2027-01-31');
        $input = new \App\Dto\ContractIndexationInput(increaseDate: '2027-01-31', currentMonthlyPayment: '100', increasePercent: '0', periodic: true, intervalMonths: 1);
        static::getContainer()->get(\App\Service\ContractService::class)->index($contract, $input);
        $service = static::getContainer()->get(ContractInterventionService::class);
        $before = $service->list(new ContractInterventionListQuery());
        $contract->setIncreaseDate(new \DateTimeImmutable('2027-02-10'));
        $this->em->flush();
        $after = $service->list(new ContractInterventionListQuery());

        self::assertSame([['expiry'], ['2027-02-10', '2027-02-15']], [array_column($before['items'], 'reason'), array_column($after['items'], 'interventionDate')]);
    }

    public function testSuccessorClosesOldExpiryButCancelledSuccessorDoesNot(): void
    {
        $previous = $this->contract('2027-03-31');
        $next = new Contract($previous->getSpace(), $this->renter, $this->object->getLandLord(), $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable('2028-03-31'), new \DateTimeImmutable('2027-04-01'), 'Next', '100');
        $this->em->persist($next);
        $this->em->flush();
        $service = static::getContainer()->get(ContractInterventionService::class);
        $before = $service->list(new ContractInterventionListQuery());
        $next->setAnnulmentDate(new \DateTimeImmutable('2027-03-30'));
        $this->em->flush();
        $after = $service->list(new ContractInterventionListQuery());

        self::assertSame([[$next->getId()], [$previous->getId()]], [array_column($before['items'], 'contractId'), array_column($after['items'], 'contractId')]);
    }

    public function testAttentionStartsConfiguredDaysBeforeUncompletedEvent(): void
    {
        $contract = $this->contract('2027-03-31');
        $service = static::getContainer()->get(ContractInterventionService::class);
        $before = $service->requiresAttention($contract->getId(), new \DateTimeImmutable('2027-02-28'));
        $window = $service->requiresAttention($contract->getId(), new \DateTimeImmutable('2027-03-01'));
        $contract->setAnnulmentDate(new \DateTimeImmutable('2027-03-31'));
        $this->em->flush();

        self::assertSame([false, true, false], [$before, $window, $service->requiresAttention($contract->getId(), new \DateTimeImmutable('2027-03-01'))]);
    }

    public function testStatusFilterUsesEffectiveInclusiveLeaseDates(): void
    {
        $active = $this->contract('2999-12-31', number: 'Active');
        $upcoming = $this->contract('2999-12-31', number: 'Upcoming');
        $upcoming->setContractDate(new \DateTimeImmutable('2999-01-01'));
        $finished = $this->contract('2021-01-01', number: 'Finished');
        $cancelled = $this->contract('2999-12-31', number: 'Cancelled');
        $cancelled->setAnnulmentDate(new \DateTimeImmutable('2019-12-31'));
        $this->em->flush();
        $service = static::getContainer()->get(\App\Service\ContractService::class);
        $ids = [];
        foreach (['active', 'upcoming', 'finished', 'cancelled'] as $status) {
            $ids[] = array_column($service->list(new \App\Dto\ContractListQuery(status: $status))['items'], 'id');
        }

        self::assertSame([[$active->getId()], [$upcoming->getId()], [$finished->getId()], [$cancelled->getId()]], $ids);
    }

    public function testAttentionDatesFollowNotificationWindowsAndHideCompletedEvents(): void
    {
        $contract = $this->contract('2027-03-31', '2027-03-15');
        $service = static::getContainer()->get(ContractInterventionService::class);
        $before = $service->attentionDates($contract->getId(), new \DateTimeImmutable('2027-02-12'));
        $window = $service->attentionDates($contract->getId(), new \DateTimeImmutable('2027-03-01'));
        $contract->setLastIndexationDate(new \DateTimeImmutable('2027-03-15'));
        $this->em->flush();
        $completed = $service->attentionDates($contract->getId(), new \DateTimeImmutable('2027-03-01'));
        $type = $this->em->find(\App\Entity\NotificationType::class, 1);
        $type->configure($type->getName(), 1, $type->getRoles(), true);
        $this->em->flush();
        $shortWindow = $service->attentionDates($contract->getId(), new \DateTimeImmutable('2027-03-01'));
        $type->configure($type->getName(), 30, $type->getRoles(), false);
        $this->em->flush();
        $disabled = $service->attentionDates($contract->getId(), new \DateTimeImmutable('2027-04-01'));

        self::assertSame(
            [
                ['expiry' => null, 'indexation' => null],
                ['expiry' => '2027-03-31', 'indexation' => '2027-03-15'],
                ['expiry' => '2027-03-31', 'indexation' => null],
                ['expiry' => null, 'indexation' => null],
                ['expiry' => null, 'indexation' => null],
            ],
            [$before, $window, $completed, $shortWindow, $disabled]
        );
    }

    public function testPeriodicAttentionUsesPendingDateInsteadOfCompletedAnchor(): void
    {
        $contract = $this->contract('2999-12-31', '2027-01-31');
        $contract->setLastIndexationDate(new \DateTimeImmutable('2027-01-31'));
        $contract->setIncreaseIntervalMonths(1);
        $this->em->flush();

        self::assertSame(
            ['expiry' => null, 'indexation' => '2027-02-28'],
            static::getContainer()->get(ContractInterventionService::class)->attentionDates($contract->getId(), new \DateTimeImmutable('2027-02-01'))
        );
    }

    public function testObjectContractsKeepUnresolvedExpiredContractsFirstAndIncludeCompletedHistory(): void
    {
        $expired = $this->contract('2021-02-01', number: 'Unrenewed');
        $indexation = $this->contract('2999-12-31', '2022-03-01', 'Indexation');
        $active = $this->contract('2999-12-31', number: 'Active');
        $previous = $this->contract('2024-12-31', number: 'Renewed');
        $successor = new Contract($previous->getSpace(), $this->renter, $this->object->getLandLord(), $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable('2999-12-31'), new \DateTimeImmutable('2025-01-01'), 'Successor', '100');
        $this->em->persist($successor);
        $cancelled = $this->contract('2999-12-31', number: 'Cancelled');
        $cancelled->setAnnulmentDate(new \DateTimeImmutable('2019-12-31'));
        $this->em->flush();
        $user = new User(bin2hex(random_bytes(8)).'@object-contracts.test');
        $user->setRoles(['ROLE_TECHNICIAN']);
        $user->setActive(true);
        $token = bin2hex(random_bytes(32));
        $this->em->persist($user);
        $this->em->persist(new AccessToken($user, $token, new \DateTimeImmutable('+1 hour')));
        $this->em->flush();
        $this->client->request('GET', '/api/objects/'.$this->object->getId().'/contracts', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            [200, 6, [$expired->getId(), $indexation->getId(), $successor->getId(), $active->getId(), $cancelled->getId(), $previous->getId()], ['2021-02-01', null, null, null, null, null], [null, '2022-03-01', null, null, null, null]],
            [$this->client->getResponse()->getStatusCode(), $result['total'] ?? null, array_column($result['items'] ?? [], 'id'), array_column(array_column($result['items'] ?? [], 'attentionDates'), 'expiry'), array_column(array_column($result['items'] ?? [], 'attentionDates'), 'indexation')]
        );
    }

    public function testObjectContractsOrderBeforePaginationAndNeverIncludeAnotherObject(): void
    {
        for ($day = 23; $day >= 1; --$day) {
            $this->contract(sprintf('2021-05-%02d', $day), number: 'N-'.$day);
        }
        $otherObject = new RentalObject($this->object->getLandLord(), '100', 'Other object', 'Address');
        $otherSpace = new Space($otherObject, 'Other space', '10');
        $otherContract = new Contract($otherSpace, $this->renter, $this->object->getLandLord(), $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable('2020-01-02'), new \DateTimeImmutable('2020-01-01'), 'Other contract', '100');
        foreach ([$otherObject, $otherSpace, $otherContract] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $service = static::getContainer()->get(\App\Service\ContractService::class);
        $first = $service->forObject($this->object, new RegistryListQuery());
        $second = $service->forObject($this->object, new RegistryListQuery(page: 2));
        $empty = $service->forObject($this->object, new RegistryListQuery(page: 3));

        self::assertSame(
            [23, 20, 'N-1', 'N-20', ['N-21', 'N-22', 'N-23'], 23, []],
            [$first['total'], count($first['items']), $first['items'][0]['contractNum'], $first['items'][19]['contractNum'], array_column($second['items'], 'contractNum'), $empty['total'], $empty['items']]
        );
    }

    public function testEndpointRequiresLoginAndIsReadableByDirector(): void
    {
        $this->contract('2027-04-01', '2027-03-05');
        $this->client->request('GET', '/api/contracts/interventions');
        $anonymous = $this->client->getResponse()->getStatusCode();
        $user = new User(bin2hex(random_bytes(8)).'@intervention.test');
        $user->setRoles(['ROLE_DIRECTOR']);
        $user->setActive(true);
        $token = bin2hex(random_bytes(32));
        $this->em->persist($user);
        $this->em->persist(new AccessToken($user, $token, new \DateTimeImmutable('+1 hour')));
        $this->em->flush();
        $this->client->request('GET', '/api/contracts/interventions', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([401, 200, 2], [$anonymous, $this->client->getResponse()->getStatusCode(), $result['total']]);
    }

    public function testAttentionCombinesKindsAndFiltersBeforePagination(): void
    {
        $rental = $this->contract('2027-12-31', '2027-01-31');
        $provider = new \App\Entity\UnityProvider('Attention supplier');
        $utility = new \App\Entity\UnityProviderContract($this->object, $provider, new \DateTimeImmutable('2028-01-01'), 'Utility-1');
        foreach ([$provider, $utility] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $service = static::getContainer()->get(ContractInterventionService::class);
        $all = $service->list(new ContractInterventionListQuery());
        $indexation = $service->list(new ContractInterventionListQuery(action: 'indexation', contractType: 'rental'));
        $suppliers = $service->list(new ContractInterventionListQuery(query: 'attention supplier', action: 'expiry', contractType: 'utility'));
        $impossible = $service->list(new ContractInterventionListQuery(action: 'indexation', contractType: 'utility'));

        self::assertSame(
            [3, ['rental', 'rental', 'utility'], ['2027-01-31', '2027-12-31', '2028-01-01'], [true, true, null], 1, [$rental->getId()], 1, null, null, 'Attention supplier', 0],
            [$all['total'], array_column($all['items'], 'contractType'), array_column($all['items'], 'interventionDate'), array_column($all['items'], 'isSigned'), $indexation['total'], array_column($indexation['items'], 'contractId'), $suppliers['total'], $suppliers['items'][0]['space'], $suppliers['items'][0]['renter'], $suppliers['items'][0]['unityProvider']['name'], $impossible['total']]
        );
    }
}
