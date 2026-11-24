<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contract;
use App\Entity\ContractType;
use App\Entity\LandLord;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\Space;
use App\Entity\User;
use App\Service\ContractNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UnsignedContractNotificationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('Separate test database required.');
        }
        $this->em->getConnection()->beginTransaction();
        $this->em->getConnection()->executeStatement('UPDATE notification_types SET is_active = (id = 5)');
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function user(array $roles, bool $blocked = false): User
    {
        $user = new User(bin2hex(random_bytes(8)).'@unsigned-notification.test');
        $user->setRoles($roles);
        $user->setActive(true);
        $user->setBlocked($blocked);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function contract(bool $signed = false, string $start = '2026-12-01', string $end = '2026-12-31'): Contract
    {
        $owner = new LandLord('Unsigned owner');
        $object = new RentalObject($owner, '100', 'Unsigned object', 'Address');
        $renter = new Renter($this->em->find(Ownership::class, 1), 'Unsigned renter');
        $space = new Space($object, '10 кв.м Unsigned renter', '10');
        $contract = new Contract($space, $renter, $owner, $this->em->find(ContractType::class, 1), '0', new \DateTimeImmutable($end), new \DateTimeImmutable($start), 'UNSIGNED-TEST', '50000');
        $contract->setSigned($signed);
        foreach ([$owner, $object, $renter, $space, $contract] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return $contract;
    }

    public function testFirstReminderStartsOnRentalDateAndLateRunCreatesOnlyCurrentPeriod(): void
    {
        $this->user(['ROLE_LAWYER']);
        $this->contract();
        $generator = static::getContainer()->get(ContractNotificationService::class);
        $before = $generator->generate(new \DateTimeImmutable('2026-11-30'));
        $start = $generator->generate(new \DateTimeImmutable('2026-12-01'));
        $sameWeek = $generator->generate(new \DateTimeImmutable('2026-12-07'));
        $late = $generator->generate(new \DateTimeImmutable('2026-12-23'));
        $days = $this->em->getConnection()->fetchFirstColumn("SELECT due_date FROM notifications WHERE kind = 'contract.unsigned' ORDER BY due_date");

        self::assertSame([0, 1, 0, 1, ['2026-12-01', '2026-12-22']], [$before, $start, $sameWeek, $late, $days]);
    }

    public function testWeeklyRepeatTargetsLawyerAndDirectorWithoutDuplicatesAfterReading(): void
    {
        $lawyer = $this->user(['ROLE_LAWYER', 'ROLE_DIRECTOR']);
        $director = $this->user(['ROLE_DIRECTOR']);
        $this->user(['ROLE_ADMIN']);
        $this->user(['ROLE_TECHNICIAN']);
        $this->user(['ROLE_LAWYER'], true);
        $contract = $this->contract();
        $generator = static::getContainer()->get(ContractNotificationService::class);
        $first = $generator->generate(new \DateTimeImmutable('2026-12-08'));
        $this->em->getConnection()->executeStatement('UPDATE notifications SET read_at = CURRENT_TIMESTAMP');
        $again = $generator->generate(new \DateTimeImmutable('2026-12-09'));
        $nextWeek = $generator->generate(new \DateTimeImmutable('2026-12-15'));
        $rows = $this->em->getConnection()->fetchAllAssociative("SELECT user_id, kind, due_date, source_path FROM notifications WHERE kind = 'contract.unsigned' ORDER BY due_date, user_id");
        $recipients = [$lawyer->getId(), $director->getId()];
        sort($recipients);
        $expected = [];
        foreach (['2026-12-08', '2026-12-15'] as $day) {
            foreach ($recipients as $id) {
                $expected[] = ['user_id' => $id, 'kind' => 'contract.unsigned', 'due_date' => $day, 'source_path' => '/contracts/'.$contract->getId()];
            }
        }

        self::assertSame([2, 0, 2, $expected], [$first, $again, $nextWeek, $rows]);
    }

    public function testSignedFutureAndCancelledContractsDoNotProduceUnsignedNotifications(): void
    {
        $this->user(['ROLE_LAWYER']);
        $this->contract(true);
        $this->contract(false, '2027-01-01', '2027-01-31');
        $cancelled = $this->contract();
        $cancelled->setAnnulmentDate(new \DateTimeImmutable('2026-11-30'));
        $this->em->flush();

        self::assertSame(0, static::getContainer()->get(ContractNotificationService::class)->generate(new \DateTimeImmutable('2026-12-08')));
    }

    public function testRepeatedGenerationReadsChangedRolesIntervalAndSwitchAndStopsAfterSigning(): void
    {
        $this->user(['ROLE_LAWYER']);
        $director = $this->user(['ROLE_DIRECTOR']);
        $contract = $this->contract();
        $generator = static::getContainer()->get(ContractNotificationService::class);
        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE notification_types SET roles = ?, days = 3 WHERE id = 5', ['["ROLE_DIRECTOR"]']);
        $first = $generator->generate(new \DateTimeImmutable('2026-12-10'));
        $again = $generator->generate(new \DateTimeImmutable('2026-12-11'));
        $db->executeStatement('UPDATE notification_types SET is_active = false WHERE id = 5');
        $off = $generator->generate(new \DateTimeImmutable('2026-12-13'));
        $db->executeStatement('UPDATE notification_types SET is_active = true WHERE id = 5');
        $db->executeStatement('UPDATE contracts SET is_signed = true WHERE id = ?', [$contract->getId()]);
        $signed = $generator->generate(new \DateTimeImmutable('2026-12-13'));

        self::assertSame([1, 0, 0, 0, [$director->getId()]], [$first, $again, $off, $signed, array_map('intval', $db->fetchFirstColumn("SELECT user_id FROM notifications WHERE kind = 'contract.unsigned'"))]);
    }
}
