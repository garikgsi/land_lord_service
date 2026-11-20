<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\AccessToken;
use App\Entity\User;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version20261119171000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require_once __DIR__.'/../../migrations/Version20261119171000.php';

final class InitializeAdminTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (!str_ends_with($this->em->getConnection()->getDatabase(), '_test')) {
            throw new \LogicException('Bootstrap fixtures require a separate test database.');
        }
        $this->em->getConnection()->executeStatement('DELETE FROM users');
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM users');
        parent::tearDown();
    }

    public function testMigrationCreatesOneInactiveSystemAccountWithoutPassword(): void
    {
        $this->migrate();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertSame([1, true, false, ''], [$this->em->getRepository(User::class)->count(), $user?->isSuperAdmin(), $user?->isActive(), $user?->getPassword()]);
    }

    public function testMigrationPreservesExistingTechnicalAccountAndRemovesOtherSystemRoles(): void
    {
        $existing = new User('admin@example.com');
        $existing->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        $existing->setFirstName('Сохранённое имя');
        $existing->setPassword('existing-fixture-hash');
        $existing->setActive(true);
        $other = new User('other@test.example');
        $other->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_LAWYER']);
        $this->em->persist($existing);
        $this->em->persist($other);
        $this->em->persist(new AccessToken($other, 'fixture-token', new \DateTimeImmutable('+1 hour')));
        $this->em->flush();
        $id = $existing->getId();
        $otherId = $other->getId();
        $this->migrate();
        $this->em->clear();
        $existing = $this->em->find(User::class, $id);
        $other = $this->em->find(User::class, $otherId);
        self::assertSame([2, 'Сохранённое имя', 'existing-fixture-hash', true, true, false, ['ROLE_LAWYER', 'ROLE_ADMIN'], 0], [$this->em->getRepository(User::class)->count(), $existing->getFirstName(), $existing->getPassword(), $existing->isActive(), $existing->isSuperAdmin(), $other->isSuperAdmin(), $other->getAssignedRoles(), $this->em->getRepository(AccessToken::class)->count()]);
    }

    public function testCommandInitializesMigratedAccountOnceAndCannotResetItsPassword(): void
    {
        $this->migrate();
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:user:initialize-admin'));
        $tester->setInputs(['initial-fixture-password']);
        $first = $tester->execute(['first-name' => 'Анна']);
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $hash = $user->getPassword();
        $second = $tester->execute(['first-name' => 'Другое имя']);
        self::assertSame([0, 1, 'Анна', true, true, true], [$first, $second, $user->getFirstName(), $user->isActive(), static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'initial-fixture-password'), $hash === $user->getPassword()]);
    }

    public function testCommandCannotCreateUsersWithoutTheInitializationMigration(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:user:initialize-admin'));
        self::assertSame([1, 0], [$tester->execute(['first-name' => 'Анна']), $this->em->getRepository(User::class)->count()]);
    }

    private function migrate(): void
    {
        $migration = new Version20261119171000($this->em->getConnection(), new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->em->getConnection()->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}
