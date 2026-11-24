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
use App\Notification\NotificationChannel;
use App\Notification\NotificationSenderInterface;
use App\Notification\PushNotificationSender;
use App\Service\ContractNotificationService;
use App\Service\ContractService;
use App\Service\NotificationDeliveryService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NotificationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private NotificationService $notifications;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('A separate test database is required.');
        }
        $this->em->getConnection()->beginTransaction();
        $this->notifications = static::getContainer()->get(NotificationService::class);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function user(array $roles = []): User
    {
        $user = new User(bin2hex(random_bytes(8)).'@notification.test');
        $user->setRoles($roles);
        $user->setActive(true);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function call(string $method, string $path, ?User $user): array
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $user) {
            $token = bin2hex(random_bytes(32));
            $this->em->persist(new AccessToken($this->em->find(User::class, $user->getId()), $token, new \DateTimeImmutable('+1 hour')));
            $this->em->flush();
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $path, server: $headers);

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function contract(string $end = '2027-03-31'): Contract
    {
        $owner = new LandLord('Owner');
        $object = new RentalObject($owner, '100', 'Object', 'Address');
        $space = new Space($object, 'Space', '50');
        $renter = new Renter($this->em->find(Ownership::class, 1), 'Renter');
        $contract = new Contract($space, $renter, $owner, $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable($end), new \DateTimeImmutable('2026-01-01'), 'N-1', '100');
        foreach ([$owner, $object, $space, $renter, $contract] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return $contract;
    }

    private function generator(): ContractNotificationService
    {
        return new ContractNotificationService($this->em, static::getContainer()->get(ContractService::class), $this->notifications, static::getContainer()->get(\App\Service\NotificationTypeService::class), ['db']);
    }

    public function testPersonalScopeIncludesNoAdministrativeBypass(): void
    {
        $first = $this->user();
        $second = $this->user(['ROLE_ADMIN']);
        $own = $this->notifications->notify($first, 'test', 'Own title', 'Own message', new \DateTimeImmutable('2027-03-31'));
        $this->notifications->notify($second, 'test', 'Other title', 'Other message');
        $list = $this->call('GET', '/api/notifications', $first);
        $count = $this->call('GET', '/api/notifications/unread-count', $first);
        $this->call('GET', '/api/notifications/'.$own->getId(), $second);
        $foreignRead = $this->client->getResponse()->getStatusCode();
        $this->call('PUT', '/api/notifications/'.$own->getId().'/read', $second);
        $foreignWrite = $this->client->getResponse()->getStatusCode();
        $calendar = $this->call('GET', '/api/notifications/calendar?start=2027-03-01&end=2027-04-01', $second);

        self::assertSame([[$own->getId()], 1, 404, 404, []], [array_column($list['items'], 'id'), $count['unreadCount'], $foreignRead, $foreignWrite, $calendar['items']]);
    }

    public function testReadIsIdempotentBulkReadIsPersonalAndReadDatesStayInCalendar(): void
    {
        $user = $this->user();
        $other = $this->user();
        $one = $this->notifications->notify($user, 'test', 'First', 'Message', new \DateTimeImmutable('2027-03-01'));
        $two = $this->notifications->notify($user, 'test', 'Second', 'Message', new \DateTimeImmutable('2027-03-31'));
        $this->notifications->notify($user, 'test', 'No date', 'Message');
        $this->notifications->notify($other, 'test', 'Other user', 'Message');
        $read = $this->call('PUT', '/api/notifications/'.$one->getId().'/read', $user);
        $again = $this->call('PUT', '/api/notifications/'.$one->getId().'/read', $user);
        $all = $this->call('PUT', '/api/notifications/read-all', $user);
        $otherCount = $this->call('GET', '/api/notifications/unread-count', $other);
        $calendar = $this->call('GET', '/api/notifications/calendar?start=2027-03-01&end=2027-04-01', $user);
        $list = $this->call('GET', '/api/notifications?status=unread', $user);

        self::assertSame(
            [true, true, 0, 1, ['notification-'.$one->getId(), 'notification-'.$two->getId()], [], '#2fb344'],
            [null !== $read['readAt'], $read['readAt'] === $again['readAt'], $all['unreadCount'], $otherCount['unreadCount'],
                array_column($calendar['items'], 'id'), $list['items'], $calendar['items'][0]['color']]
        );
    }

    public function testDeduplicationIsPerRecipientAndChannelsHaveIndependentOutboxRows(): void
    {
        $user = $this->user();
        $first = $this->notifications->notify($user, 'test', 'First title', 'First body', channels: [NotificationChannel::Db, NotificationChannel::Email, NotificationChannel::Push, NotificationChannel::Db], deduplicationKey: 'event-1');
        $again = $this->notifications->notify($user, 'test', 'Changed title', 'Changed body', deduplicationKey: 'event-1');
        $other = $this->notifications->notify($this->user(), 'test', 'Other', 'Other', deduplicationKey: 'event-1');
        $channels = $this->em->getConnection()->fetchAllKeyValue('SELECT channel, status FROM notification_deliveries WHERE notification_id = ? ORDER BY channel', [$first->getId()]);

        self::assertSame(
            [true, true, 'First title', ['db' => 'sent', 'email' => 'pending', 'push' => 'pending']],
            [$first->getId() === $again->getId(), $other->getId() !== $first->getId(), $again->getTitle(), $channels]
        );
    }

    public function testListFiltersPaginationAndCalendarBoundaries(): void
    {
        $user = $this->user();
        for ($index = 0; $index < 21; ++$index) {
            $this->notifications->notify($user, 'test', 'Title '.$index, 'Message', new \DateTimeImmutable(20 === $index ? '2027-04-01' : '2027-03-31'));
        }
        $first = $this->call('GET', '/api/notifications', $user);
        $second = $this->call('GET', '/api/notifications?page=2', $user);
        $search = $this->call('GET', '/api/notifications?query=Title%2020', $user);
        $calendar = $this->call('GET', '/api/notifications/calendar?start=2027-03-31&end=2027-04-01', $user);
        $this->call('GET', '/api/notifications?status=invalid', $user);

        self::assertSame([21, 20, 1, 'Title 20', 1, 20, 422], [$first['total'], count($first['items']), count($second['items']), $first['items'][0]['title'], $search['total'], count($calendar['items']), $this->client->getResponse()->getStatusCode()]);
    }

    public function testAnonymousAccessAndPublicCreationAreUnavailable(): void
    {
        $statuses = [];
        foreach (['/api/notifications', '/api/notifications/unread-count', '/api/notifications/calendar?start=2027-01-01&end=2027-02-01'] as $path) {
            $this->call('GET', $path, null);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }
        $this->call('POST', '/api/notifications', $this->user());
        $statuses[] = $this->client->getResponse()->getStatusCode();

        self::assertSame([401, 401, 401, 405], $statuses);
    }

    #[DataProvider('leadDays')]
    public function testConfiguredThirtyDayLeadUsesExactDays(string $due, string $notify): void
    {
        self::assertSame($notify, $this->generator()->notificationDate(new \DateTimeImmutable($due))->format('Y-m-d'));
    }

    public static function leadDays(): iterable
    {
        yield ['2027-03-31', '2027-03-01'];
        yield ['2028-03-31', '2028-03-01'];
        yield ['2027-05-31', '2027-05-01'];
        yield ['2027-01-31', '2027-01-01'];
    }

    public function testGeneratorNotifiesOnlyActiveUnblockedLawyersAndDoesNotDuplicate(): void
    {
        $lawyer = $this->user(['ROLE_LAWYER']);
        $this->user(['ROLE_LAWYER', 'ROLE_ACCOUNTANT']);
        $this->user(['ROLE_ADMIN']);
        $blocked = $this->user(['ROLE_LAWYER']);
        $blocked->setBlocked(true);
        $inactive = $this->user(['ROLE_LAWYER']);
        $inactive->setActive(false);
        $contract = $this->contract();
        $contract->setIncreaseDate(new \DateTimeImmutable('2027-03-15'));
        $contract->setIncreasePercent('5');
        $this->em->flush();
        $early = $this->generator()->generate(new \DateTimeImmutable('2027-02-12'));
        $indexations = $this->generator()->generate(new \DateTimeImmutable('2027-02-13'));
        $expiries = $this->generator()->generate(new \DateTimeImmutable('2027-03-01'));
        $again = $this->generator()->generate(new \DateTimeImmutable('2027-03-01'));
        $messages = $this->call('GET', '/api/notifications', $lawyer);

        self::assertSame([0, 2, 2, 0, 2, ['contract.expiry', 'contract.indexation']], [$early, $indexations, $expiries, $again, $messages['total'], array_column($messages['items'], 'kind')]);
    }

    public function testPeriodicIndexationCreatesRemindersWithoutChangingRent(): void
    {
        $lawyer = $this->user(['ROLE_LAWYER']);
        $contract = $this->contract('2027-12-31');
        $contract->setIncreaseDate(new \DateTimeImmutable('2027-01-31'));
        $contract->setIncreasePercent('5');
        $contract->setIncreaseIntervalMonths(1);
        $this->em->flush();
        $first = $this->generator()->generate(new \DateTimeImmutable('2027-01-29'));
        $second = $this->generator()->generate(new \DateTimeImmutable('2027-03-01'));
        $dates = $this->em->getConnection()->fetchFirstColumn("SELECT due_date FROM notifications WHERE user_id = ? AND kind = 'contract.indexation' ORDER BY due_date", [$lawyer->getId()]);

        self::assertSame([2, 1, ['2027-01-31', '2027-02-28', '2027-03-31'], '100'], [$first, $second, $dates, $contract->getMonthlyPayment()]);
    }

    public function testEffectiveAnnulmentDateReplacesExpiryAndCancelledContractsDoNotNotify(): void
    {
        $this->user(['ROLE_LAWYER']);
        $ending = $this->contract();
        $ending->setAnnulmentDate(new \DateTimeImmutable('2027-03-05'));
        $cancelled = $this->contract();
        $cancelled->setContractDate(new \DateTimeImmutable('2027-03-01'));
        $cancelled->setAnnulmentDate(new \DateTimeImmutable('2027-02-28'));
        $this->em->flush();
        $count = $this->generator()->generate(new \DateTimeImmutable('2027-02-05'));

        self::assertSame([1, ['2027-03-05']], [$count, $this->em->getConnection()->fetchFirstColumn('SELECT due_date FROM notifications')]);
    }

    public function testFailedChannelRetriesIndependentlyAndDeferredPushCanBeResumed(): void
    {
        $user = $this->user();
        $record = $this->notifications->notify($user, 'test', 'Delivery', 'Message', channels: [NotificationChannel::Db, NotificationChannel::Email, NotificationChannel::Push]);
        $email = new class implements NotificationSenderInterface {
            public bool $fail = true;
            public int $sent = 0;

            public function channel(): NotificationChannel { return NotificationChannel::Email; }

            public function send(\App\Entity\NotificationDelivery $delivery): void
            {
                if ($this->fail) { throw new \RuntimeException('Simulated delivery failure'); }
                ++$this->sent;
            }
        };
        $worker = new NotificationDeliveryService($this->em, new NullLogger(), [$email, new PushNotificationSender()]);
        $first = $worker->run();
        $db = $this->em->getConnection();
        $pushAttempts = $db->fetchOne("SELECT attempt_count FROM notification_deliveries WHERE notification_id = ? AND channel = 'push'", [$record->getId()]);
        $db->executeStatement("UPDATE notification_deliveries SET next_attempt_at = '2000-01-01' WHERE notification_id = ?", [$record->getId()]);
        $email->fail = false;
        $second = $worker->run();
        $statuses = $db->fetchAllKeyValue('SELECT channel, status FROM notification_deliveries WHERE notification_id = ? ORDER BY channel', [$record->getId()]);

        self::assertSame(
            [['sent' => 0, 'failed' => 1, 'deferred' => 1], ['sent' => 1, 'failed' => 0, 'deferred' => 0], 0, 1, ['db' => 'sent', 'email' => 'sent', 'push' => 'deferred']],
            [$first, $second, $pushAttempts, $email->sent, $statuses]
        );
    }

    public function testWorkerReclaimsExpiredLeaseAndDoesNotClaimLiveLease(): void
    {
        $record = $this->notifications->notify($this->user(), 'test', 'Lease', 'Message', channels: [NotificationChannel::Email]);
        $db = $this->em->getConnection();
        $db->executeStatement("UPDATE notification_deliveries SET status = 'processing', attempt_count = 1, locked_until = '2999-01-01' WHERE notification_id = ?", [$record->getId()]);
        $sender = new class implements NotificationSenderInterface {
            public function channel(): NotificationChannel { return NotificationChannel::Email; }

            public function send(\App\Entity\NotificationDelivery $delivery): void {}
        };
        $worker = new NotificationDeliveryService($this->em, new NullLogger(), [$sender]);
        $live = $worker->run();
        $db->executeStatement("UPDATE notification_deliveries SET locked_until = '2000-01-01' WHERE notification_id = ?", [$record->getId()]);
        $expired = $worker->run();

        self::assertSame([0, 1, 'sent', 2], [$live['sent'], $expired['sent'], $db->fetchOne('SELECT status FROM notification_deliveries WHERE notification_id = ?', [$record->getId()]), $db->fetchOne('SELECT attempt_count FROM notification_deliveries WHERE notification_id = ?', [$record->getId()])]);
    }
}
