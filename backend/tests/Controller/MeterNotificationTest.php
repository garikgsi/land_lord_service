<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\LandLord;
use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Entity\ObjectMeter;
use App\Entity\RentalObject;
use App\Entity\Space;
use App\Entity\SpaceMeter;
use App\Entity\Unity;
use App\Entity\User;
use App\Service\MeterNotificationService;
use App\Service\MeterScheduleService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MeterNotificationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RentalObject $object;
    private Space $space;
    private Unity $water;
    private Unity $electricity;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('A separate test database is required.');
        }

        $this->em->getConnection()->beginTransaction();
        $owner = new LandLord('Owner');
        $this->object = new RentalObject($owner, '100', 'Notification object', 'Address');
        $this->space = new Space($this->object, 'Space', '10');
        $this->water = new Unity('Вода', true);
        $this->electricity = new Unity('Электричество', true);

        foreach ([$owner, $this->object, $this->space, $this->water, $this->electricity] as $record) {
            $this->em->persist($record);
        }

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function generator(): MeterNotificationService
    {
        return new MeterNotificationService(
            $this->em,
            static::getContainer()->get(MeterScheduleService::class),
            static::getContainer()->get(NotificationService::class),
            static::getContainer()->get(\App\Service\NotificationTypeService::class),
            ['db'],
        );
    }

    private function user(array $roles = ['ROLE_TECHNICIAN'], bool $active = true, bool $blocked = false): User
    {
        $user = new User(bin2hex(random_bytes(8)).'@meter-notification.test');
        $user->setRoles($roles);
        $user->setActive($active);
        $user->setBlocked($blocked);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function meter(Unity $unity, ?int $day = null, bool $inSpace = false, bool $installed = true, ?RentalObject $object = null): Meter
    {
        $meter = new Meter($unity, new \DateTimeImmutable('2026-01-01'), 0, 365, readingDay: $day);
        $this->em->persist($meter);

        if ($installed) {
            $this->em->persist($inSpace ? new SpaceMeter($this->space, $meter) : new ObjectMeter($object ?? $this->object, $meter));
        }

        $this->em->flush();

        return $meter;
    }

    public function testOneObjectDateMessageAggregatesFiveWaterAndFiveElectricityMeters(): void
    {
        $technician = $this->user();
        $this->user(['ROLE_ACCOUNTANT', 'ROLE_TECHNICIAN']);
        $this->user(['ROLE_ADMIN']);
        $this->user(['ROLE_LAWYER']);
        $this->user(active: false);
        $this->user(blocked: true);

        for ($index = 0; $index < 5; ++$index) {
            $this->meter($this->water, inSpace: $index < 2);
            $this->meter($this->electricity, inSpace: $index < 2);
        }

        $early = $this->generator()->generate(new \DateTimeImmutable('2027-03-17'));
        $first = $this->generator()->generate(new \DateTimeImmutable('2027-03-18'));
        $again = $this->generator()->generate(new \DateTimeImmutable('2027-03-19'));
        $messages = $this->em->getConnection()->fetchAllAssociative('SELECT kind, message, due_date, source_path FROM notifications WHERE user_id = ?', [$technician->getId()]);
        $calendar = static::getContainer()->get(NotificationService::class)->calendar($technician, new \DateTimeImmutable('2027-03-01'), new \DateTimeImmutable('2027-04-01'));

        self::assertSame(
            [0, 2, 0, 1, 'meter.reading', true, true, '2027-03-25', '/objects/'.$this->object->getId(), 1],
            [$early, $first, $again, count($messages), $messages[0]['kind'], str_contains($messages[0]['message'], 'Вода — 5'), str_contains($messages[0]['message'], 'Электричество — 5'),
                $messages[0]['due_date'], $messages[0]['source_path'], count($calendar)]
        );
    }

    public function testOnlyMissingCurrentMonthReadingsAreCountedAndRemovedMetersAreExcluded(): void
    {
        $this->user();
        $this->meter($this->water);
        $submitted = $this->meter($this->water);
        $this->em->persist(new MeterReading($submitted, new \DateTimeImmutable('2027-03-10'), 100));
        $this->meter($this->water, installed: false);
        $lastMonthOnly = $this->meter($this->electricity, inSpace: true);
        $this->em->persist(new MeterReading($lastMonthOnly, new \DateTimeImmutable('2027-02-25'), 100));
        $this->em->flush();
        $created = $this->generator()->generate(new \DateTimeImmutable('2027-03-18'));
        $message = $this->em->getConnection()->fetchOne("SELECT message FROM notifications WHERE kind = 'meter.reading'");

        self::assertSame([1, true, true], [$created, str_contains($message, 'Вода — 1'), str_contains($message, 'Электричество — 1')]);
    }

    public function testDifferentObjectsAndDeadlinesDoNotMerge(): void
    {
        $this->user();
        $this->meter($this->water, 20);
        $this->meter($this->water, 25);
        $other = new RentalObject($this->object->getLandLord(), '100', 'Other object', 'Address');
        $this->em->persist($other);
        $this->em->flush();
        $this->meter($this->electricity, 20, object: $other);
        $first = $this->generator()->generate(new \DateTimeImmutable('2027-03-13'));
        $second = $this->generator()->generate(new \DateTimeImmutable('2027-03-18'));
        $rows = $this->em->getConnection()->fetchAllAssociative("SELECT due_date, source_path FROM notifications WHERE kind = 'meter.reading' ORDER BY due_date, source_path");

        self::assertSame(
            [2, 1, ['2027-03-20', '2027-03-20', '2027-03-25'], 2],
            [$first, $second, array_column($rows, 'due_date'), count(array_unique(array_column($rows, 'source_path')))]
        );
    }

    public function testShortMonthNextMonthDeadlineAndMissedRunCatchup(): void
    {
        $this->user();
        $this->meter($this->water, 31);
        $this->meter($this->electricity, 1);
        $first = $this->generator()->generate(new \DateTimeImmutable('2027-02-21'));
        $next = $this->generator()->generate(new \DateTimeImmutable('2027-02-22'));
        $again = $this->generator()->generate(new \DateTimeImmutable('2027-02-25'));
        $dates = $this->em->getConnection()->fetchFirstColumn("SELECT due_date FROM notifications WHERE kind = 'meter.reading' ORDER BY due_date");

        self::assertSame([1, 1, 0, ['2027-02-28', '2027-03-01']], [$first, $next, $again, $dates]);
    }

    public function testNoMessageWhenAllReadingsPresentAndNoObsoleteReminderAfterDeadline(): void
    {
        $this->user();
        $meter = $this->meter($this->water);
        $this->em->persist(new MeterReading($meter, new \DateTimeImmutable('2027-03-15'), 100));
        $this->em->flush();
        $completed = $this->generator()->generate(new \DateTimeImmutable('2027-03-18'));
        $this->meter($this->electricity);
        $expired = $this->generator()->generate(new \DateTimeImmutable('2027-03-26'));

        self::assertSame([0, 0], [$completed, $expired]);
    }

    public function testMissedInitialDayIsCaughtUpBeforeDeadlineAndDisablingMeterFlagKeepsObligation(): void
    {
        $this->user();
        $this->meter($this->water);
        $this->water->setUseMeter(false);
        $this->em->flush();

        self::assertSame(1, $this->generator()->generate(new \DateTimeImmutable('2027-03-22')));
    }

    public function testWorkerCommandGeneratesMeterRemindersWithDatabaseDelivery(): void
    {
        $recipient = $this->user();
        $this->object->setReadingDay((int) date('j'));
        $this->em->flush();
        $this->meter($this->water);
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($application->find('app:notifications:generate'));
        $status = $tester->execute([]);
        $notification = $this->em->getConnection()->fetchAssociative("SELECT id, due_date FROM notifications WHERE user_id = ? AND kind = 'meter.reading'", [$recipient->getId()]);
        $delivery = $this->em->getConnection()->fetchOne("SELECT status FROM notification_deliveries WHERE notification_id = ? AND channel = 'db'", [$notification['id'] ?? 0]);

        self::assertSame([0, date('Y-m-d'), 'sent'], [$status, $notification['due_date'] ?? null, $delivery]);
    }
}
