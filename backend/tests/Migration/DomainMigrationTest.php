<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DomainMigrationTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get('doctrine')->getConnection();
        if (!str_ends_with($this->connection->getDatabase(), '_test')) {
            throw new \LogicException('Domain migration tests require a separate test database.');
        }
        // PostgreSQL DDL and the separate migration metadata are rolled back with the fixtures.
        $this->connection->beginTransaction();
        $factory = static::getContainer()->get('doctrine.migrations.dependency_factory');
        $factory->getMigrator()->migrate($factory->getMigrationPlanCalculator()->getPlanUntilVersion(new Version('DoctrineMigrations\\Version20261119171000')), new MigratorConfiguration());
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testObjectSupplierMigrationPreservesIdsAndLegacyVersionsAndCanBeReapplied(): void
    {
        $this->migrate('DomainMigrations\\Version20261121160000');
        $this->fixtures();
        $provider = $this->connection->fetchAssociative('SELECT id, unity_id FROM unity_providers LIMIT 1');
        $linkId = $this->connection->fetchOne('SELECT id FROM object_unity_providers WHERE unity_provider_id = ?', [$provider['id']]);
        $this->connection->executeStatement("UPDATE unity_providers SET name = 'Renamed supplier' WHERE id = ?", [$provider['id']]);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM unity_providers_history WHERE id = ? ORDER BY valid_to', [$provider['id']]);
        $this->migrate('DomainMigrations\\Version20261121170000');
        $mapped = $this->connection->fetchAssociative('SELECT id, unity_id FROM object_unity_providers WHERE id = ?', [$linkId]);
        $after = $this->connection->fetchAllAssociative('SELECT * FROM unity_providers_history WHERE id = ? ORDER BY valid_to', [$provider['id']]);
        $this->migrate('DomainMigrations\\Version20261121160000');
        $restored = $this->connection->fetchOne('SELECT unity_id FROM unity_providers WHERE id = ?', [$provider['id']]);
        $this->migrate('DomainMigrations\\Version20261121170000');

        self::assertSame([$linkId, $provider['unity_id'], true, $provider['unity_id']], [$mapped['id'], $mapped['unity_id'], $before === $after, $restored]);
    }

    public function testMeterVersionMigrationPreservesLegacyVerificationsAndRollback(): void
    {
        $this->migrate('DomainMigrations\\Version20261121150000');
        $this->fixtures();
        $id = $this->connection->fetchOne('SELECT id FROM meters WHERE start_read = 120');
        $this->migrate('DomainMigrations\\Version20261121160000');
        $dates = $this->connection->fetchFirstColumn('SELECT verification_date FROM meter_verifications WHERE meter_id = ?', [$id]);
        $this->connection->executeStatement("UPDATE meters SET serial_number = 'Changed' WHERE id = ?", [$id]);
        $old = $this->connection->fetchAssociative('SELECT serial_number, valid_from, valid_to FROM meters_history WHERE id = ?', [$id]);
        $current = $this->connection->fetchOne('SELECT valid_from FROM meters WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261121150000');
        $this->migrate('DomainMigrations\\Version20261121160000');
        $after = $this->connection->fetchFirstColumn('SELECT verification_date FROM meter_verifications WHERE meter_id = ?', [$id]);

        self::assertSame([['2026-01-01'], null, null, true, ['2026-01-01']], [$dates, $old['serial_number'], $old['valid_from'], $old['valid_to'] === $current, $after]);
    }

    public function testNotificationMigrationPreservesUsersAndSupportsRollbackAndReapply(): void
    {
        $this->migrate('DomainMigrations\\Version20261121140000');
        $userId = $this->connection->fetchOne("INSERT INTO users (email, password, roles, blocked, active, createdat) VALUES ('notifications@test.example', '', '[]', false, false, CURRENT_TIMESTAMP) RETURNING id");
        $users = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->migrate('DomainMigrations\\Version20261121150000');
        $id = $this->connection->fetchOne("INSERT INTO notifications (user_id, kind, title, message, due_date, created_at) VALUES (?, 'test', 'Title', 'Body', '2027-03-31', '2027-02-28 00:00:00') RETURNING id", [$userId]);
        $this->connection->executeStatement("INSERT INTO notification_deliveries (notification_id, channel, status, attempt_count, next_attempt_at) VALUES (?, 'db', 'sent', 0, '2027-02-28')", [$id]);
        $due = $this->connection->fetchOne('SELECT due_date FROM notifications WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261121140000');
        $after = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->migrate('DomainMigrations\\Version20261121150000');

        self::assertSame(['2027-03-31', true, 0], [$due, $users === $after, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM notifications')]);
    }

    public function testPolymorphicColourMigrationPreservesValuesWindowsAndRollback(): void
    {
        $this->migrate('DomainMigrations\\Version20261120140000');
        $id = $this->connection->fetchOne("INSERT INTO land_lords (name, color) VALUES ('Legacy', '#12abef') RETURNING id");
        $this->migrate('DomainMigrations\\Version20261121120000');
        $this->connection->executeStatement("UPDATE land_lords SET color = '#d63939' WHERE id = ?", [$id]);
        $this->connection->executeStatement("UPDATE land_lords SET name = 'Renamed' WHERE id = ?", [$id]);
        $before = $this->connection->fetchAllAssociative('SELECT id, name, color, valid_from, valid_to FROM land_lords_history WHERE id = ? ORDER BY valid_to', [$id]);
        $this->migrate('DomainMigrations\\Version20261121140000');
        $color = $this->connection->fetchOne("SELECT color FROM entity_colors WHERE entity_type = 'land_lord' AND entity_id = ?", [$id]);
        $this->migrate('DomainMigrations\\Version20261121130000');
        $restored = $this->connection->fetchAllAssociative('SELECT id, name, color, valid_from, valid_to FROM land_lords_history WHERE id = ? ORDER BY valid_to', [$id]);
        $this->migrate('DomainMigrations\\Version20261121140000');
        $this->connection->executeStatement("UPDATE entity_colors SET color = NULL WHERE entity_type = 'land_lord' AND entity_id = ?", [$id]);
        $this->migrate('DomainMigrations\\Version20261121130000');
        $reset = $this->connection->fetchOne('SELECT color FROM land_lords WHERE id = ?', [$id]);
        $historical = $this->connection->fetchFirstColumn('SELECT color FROM land_lords_history WHERE id = ? ORDER BY valid_to', [$id]);
        $this->migrate('DomainMigrations\\Version20261121140000');

        self::assertSame(
            ['#d63939', true, null, ['#12abef', '#d63939', '#d63939']],
            [$color, $before === $restored, $reset, $historical]
        );
    }

    public function testSnapshotMigrationPreservesLegacyRowsAndCanBeReapplied(): void
    {
        $this->migrate('DomainMigrations\\Version20261120140000');
        $id = $this->connection->fetchOne("INSERT INTO land_lords (name) VALUES ('Legacy') RETURNING id");
        $this->migrate('DomainMigrations\\Version20261121120000');
        $legacy = $this->connection->fetchOne('SELECT valid_from FROM land_lords WHERE id = ?', [$id]);
        $this->connection->executeStatement("UPDATE land_lords SET name = 'Changed' WHERE id = ?", [$id]);
        $old = $this->connection->fetchAssociative('SELECT name, valid_from, valid_to FROM land_lords_history WHERE id = ?', [$id]);
        $current = $this->connection->fetchOne('SELECT valid_from FROM land_lords WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261120140000');
        $kept = $this->connection->fetchOne('SELECT name FROM land_lords WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261121120000');

        self::assertSame(
            [null, 'Legacy', null, true, 'Changed', 0],
            [$legacy, $old['name'], $old['valid_from'], $old['valid_to'] === $current, $kept,
                (int) $this->connection->fetchOne('SELECT COUNT(*) FROM land_lords_history')]
        );
    }

    public function testLandLordColorMigrationPreservesRecordsAndCanBeReapplied(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $id = $this->connection->fetchOne("INSERT INTO land_lords (name) VALUES ('Colour test') RETURNING id");
        $this->migrate('DomainMigrations\\Version20261120140000');
        $initial = $this->connection->fetchOne('SELECT color FROM land_lords WHERE id = ?', [$id]);
        $this->connection->executeStatement("UPDATE land_lords SET color = '#12abef' WHERE id = ?", [$id]);
        $saved = $this->connection->fetchOne('SELECT color FROM land_lords WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261120130000');
        $name = $this->connection->fetchOne('SELECT name FROM land_lords WHERE id = ?', [$id]);
        $this->migrate('DomainMigrations\\Version20261120140000');

        self::assertSame([null, '#12abef', 'Colour test', null], [$initial, $saved, $name, $this->connection->fetchOne('SELECT color FROM land_lords WHERE id = ?', [$id])]);
    }

    public function testMigrationsPersistLinkedRegistryRecordsWithOptionalFields(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT l.name AS landlord, o.name AS object_name, s.name AS space_name,
                   r.inn, c.contract_num, rc.patronymic, om.comment AS object_comment,
                   sm.comment AS space_comment, u.name AS utility, up.name AS provider,
                   m.start_read, mr.meter_read, mr.meter_reading_date
            FROM contracts c
            JOIN spaces s ON s.id = c.space_id
            JOIN objects o ON o.id = s.object_id
            JOIN land_lords l ON l.id = o.land_lord_id
            JOIN renters r ON r.id = c.renter_id
            JOIN renter_contacts rc ON rc.renter_id = r.id
            JOIN space_meters sm ON sm.space_id = s.id
            JOIN meters m ON m.id = sm.meter_id
            JOIN object_meters om ON om.object_id = o.id
            JOIN meter_readings mr ON mr.meter_id = m.id
            JOIN unities u ON u.id = m.utility_id
            JOIN unity_providers up ON up.unity_id = u.id
            JOIN object_unity_providers oup ON oup.unity_provider_id = up.id AND oup.object_id = o.id
            SQL);
        self::assertSame(['Арендодатель', 'Объект', 'Выделенная площадь', '0012345678', '0001-А', null, null, null, 'Электричество', 'Поставщик', 120, 145, '2026-11-19 12:30:00'], array_values($row));
    }

    public function testAmountsAreasAndPercentKeepDecimalPrecision(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $fraction = '0.12345678901234567890123456789';
        $this->connection->executeStatement('UPDATE objects SET square = ?', [$fraction]);
        $this->connection->executeStatement('UPDATE spaces SET square = ?', [$fraction]);
        $this->connection->executeStatement('UPDATE contracts SET security_payment = ?, monthly_payment = ?, increase_percent = ?', ['0.1', '0.2', $fraction]);
        self::assertSame(['0.3', $fraction, $fraction, $fraction], $this->connection->fetchNumeric('SELECT c.security_payment + c.monthly_payment, o.square, s.square, c.increase_percent FROM contracts c JOIN spaces s ON s.id = c.space_id JOIN objects o ON o.id = s.object_id'));
    }

    public function testContractAnnulmentHasItsOwnOptionalDateAndPreservesOriginalDates(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $before = $this->connection->fetchOne('SELECT annulment_date FROM contracts');
        $this->connection->executeStatement("UPDATE contracts SET annulment_date = '2027-01-20'");
        self::assertSame([null, ['2026-11-19', '2027-11-19', '2027-01-20']], [$before, $this->connection->fetchNumeric('SELECT contract_date, expiry_date, annulment_date FROM contracts')]);
    }

    public function testMeterSerialNumberIsOptionalAndKeepsLeadingZeros(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $before = $this->connection->fetchOne('SELECT serial_number FROM meters WHERE start_read = 120');
        $this->connection->executeStatement("UPDATE meters SET serial_number = '00012-А' WHERE start_read = 120");
        self::assertSame([null, ['00012-А', '2026-01-01', 120]], [$before, $this->connection->fetchNumeric('SELECT serial_number, create_date, start_read FROM meters WHERE start_read = 120')]);
    }

    public function testMigrationsCanBeRolledBackAndReappliedWithoutChangingApplicationTables(): void
    {
        $before = $this->connection->createSchemaManager()->listTableNames();
        $users = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $this->migrate('0');
        $after = $this->connection->createSchemaManager()->listTableNames();
        $after = array_values(array_diff($after, ['domain_test_migration_versions']));
        sort($before);
        sort($after);
        $this->migrate('DomainMigrations\\Version20261120130000');
        self::assertSame([$before, $users, 0, 5], [$after, $this->connection->fetchOne('SELECT COUNT(*) FROM users'), $this->connection->fetchOne('SELECT COUNT(*) FROM meter_readings'), $this->connection->fetchOne('SELECT COUNT(*) FROM domain_test_migration_versions')]);
    }

    #[DataProvider('missingParentStatements')]
    public function testProposedParentLinksRejectMissingParents(string $sql): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $this->connection->executeStatement('DELETE FROM object_meters');
        $this->connection->executeStatement('DELETE FROM space_meters');
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->connection->executeStatement($sql);
    }

    public static function missingParentStatements(): iterable
    {
        yield 'object landlord' => ["INSERT INTO objects (land_lord_id, square, name, address, reading_day) VALUES (2147483647, 100, 'Тест', 'Адрес', 25)"];
        yield 'space object' => ["INSERT INTO spaces (object_id, name, square) VALUES (2147483647, 'Тест', 10)"];
        yield 'object meter object' => ['INSERT INTO object_meters (object_id, meter_id) SELECT 2147483647, id FROM meters'];
        yield 'space meter space' => ['INSERT INTO space_meters (space_id, meter_id) SELECT 2147483647, id FROM meters'];
    }

    public function testDomainMigrationsAreDiscoveredByTheApplicationMigrationConfiguration(): void
    {
        $registered = static::getContainer()->get('doctrine.migrations.dependency_factory')->getMigrationRepository()->getMigrations();
        self::assertSame([true, true, true, true, true], array_map(static fn (string $version): bool => $registered->hasMigration(new Version('DomainMigrations\\'.$version)), ['Version20261119215000', 'Version20261119215100', 'Version20261119215200', 'Version20261120120000', 'Version20261120130000']));
    }

    public function testObjectReadingDayAndContractTypeAreRequiredWithoutDatabaseDefaults(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        self::assertSame([
            ['table_name' => 'contracts', 'is_nullable' => 'NO', 'column_default' => null],
            ['table_name' => 'objects', 'is_nullable' => 'NO', 'column_default' => null],
        ], $this->connection->fetchAllAssociative("SELECT table_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'public' AND ((table_name = 'objects' AND column_name = 'reading_day') OR (table_name = 'contracts' AND column_name = 'contract_type_id')) ORDER BY table_name"));
    }

    public function testAgreedCataloguesAndOptionalContractFields(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $ownerships = $this->connection->fetchFirstColumn('SELECT name FROM ownerships ORDER BY id');
        $types = $this->connection->fetchFirstColumn('SELECT name FROM contract_types ORDER BY id');
        $this->fixtures();
        $this->connection->executeStatement('UPDATE renter_contacts SET last_name = NULL, phone = NULL, email = NULL');
        $this->connection->executeStatement('UPDATE contracts SET security_payment = 0, increase_date = NULL, increase_percent = NULL');
        $this->connection->executeStatement('UPDATE meters SET verification_date = NULL');
        self::assertSame(
            [['Юридическое лицо', 'Индивидуальный предприниматель', 'Физическое лицо'], ['аренда', 'юр.адрес'], [0, null, null], [25, null]],
            [$ownerships, $types, $this->connection->fetchNumeric('SELECT security_payment::int, increase_date, increase_percent FROM contracts'), $this->connection->fetchNumeric('SELECT o.reading_day, m.reading_day FROM objects o CROSS JOIN meters m LIMIT 1')]
        );
    }

    #[DataProvider('invalidAgreedRecords')]
    public function testAgreedDatabaseRulesRejectInvalidRecords(string $sql, string $state): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $actual = 'accepted';
        try {
            $this->connection->executeStatement($sql);
        } catch (\Doctrine\DBAL\Exception\DriverException $exception) {
            $actual = $exception->getSQLState();
        }
        self::assertSame($state, $actual);
    }

    public static function invalidAgreedRecords(): iterable
    {
        yield 'object reading day required' => ['UPDATE objects SET reading_day = NULL', '23502'];
        yield 'contract type required' => ['UPDATE contracts SET contract_type_id = NULL', '23502'];
        yield 'missing object reading day' => ["INSERT INTO objects (land_lord_id, square, name, address) SELECT land_lord_id, 10, 'Test', 'Address' FROM objects", '23502'];
        yield 'missing contract type' => ['INSERT INTO contracts (space_id, renter_id, land_lord_id, security_payment, expiry_date, contract_date, contract_num, monthly_payment) SELECT space_id, renter_id, land_lord_id, security_payment, expiry_date, contract_date, contract_num, monthly_payment FROM contracts', '23502'];
        yield 'duplicate INN' => ["INSERT INTO renters (ownership_id, name, inn) SELECT ownership_id, 'Duplicate', inn FROM renters", '23505'];
        yield 'incomplete indexation' => ['UPDATE contracts SET increase_date = NULL', '23514'];
        yield 'shared end day is overlap' => ["INSERT INTO contracts (space_id, renter_id, land_lord_id, contract_type_id, security_payment, expiry_date, contract_date, contract_num, monthly_payment) SELECT space_id, renter_id, land_lord_id, contract_type_id, 0, '2028-01-01', expiry_date, contract_num, monthly_payment FROM contracts", '23P01'];
        yield 'one meter cannot be installed twice' => ['INSERT INTO object_meters (object_id, meter_id) SELECT s.object_id, sm.meter_id FROM space_meters sm JOIN spaces s ON s.id = sm.space_id', '23514'];
        yield 'same reading time' => ['INSERT INTO meter_readings (meter_id, meter_reading_date, meter_read) SELECT meter_id, meter_reading_date, meter_read FROM meter_readings', '23505'];
        yield 'new reading cannot decrease' => ["INSERT INTO meter_readings (meter_id, meter_reading_date, meter_read) SELECT meter_id, '2026-12-01', 140 FROM meter_readings", '23514'];
        yield 'backdated reading cannot exceed following reading' => ["INSERT INTO meter_readings (meter_id, meter_reading_date, meter_read) SELECT meter_id, '2026-10-01', 150 FROM meter_readings", '23514'];
    }

    public function testBackdatedReadingsCanBeInsertedAndCorrectedAndMeterCanBeRemoved(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $meter = $this->connection->fetchOne('SELECT meter_id FROM meter_readings');
        $this->connection->insert('meter_readings', ['meter_id' => $meter, 'meter_reading_date' => '2026-10-01 00:00:00', 'meter_read' => 130]);
        $this->connection->executeStatement("UPDATE meter_readings SET meter_read = 135 WHERE meter_reading_date = '2026-10-01'");
        $this->connection->executeStatement('DELETE FROM space_meters WHERE meter_id = ?', [$meter]);
        $this->connection->executeStatement('UPDATE unities SET use_meter = false');
        self::assertSame([[135, 145], 0, 1], [
            $this->connection->fetchFirstColumn('SELECT meter_read FROM meter_readings ORDER BY meter_reading_date'),
            $this->connection->fetchOne('SELECT COUNT(*) FROM space_meters'),
            $this->connection->fetchOne('SELECT COUNT(*) FROM meters WHERE id = ?', [$meter]),
        ]);
    }

    public function testCancellationBeforeStartDoesNotBlockFollowingContractAndNumbersCanRepeat(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $this->connection->executeStatement("UPDATE contracts SET annulment_date = '2026-11-01'");
        $this->connection->executeStatement('INSERT INTO contracts (space_id, renter_id, land_lord_id, contract_type_id, security_payment, expiry_date, contract_date, contract_num, monthly_payment) SELECT space_id, renter_id, land_lord_id, contract_type_id, 0, expiry_date, contract_date, contract_num, monthly_payment FROM contracts');
        self::assertSame([2, 1], $this->connection->fetchNumeric('SELECT COUNT(*), COUNT(DISTINCT contract_num) FROM contracts'));
    }

    public function testNullInnContactsVerificationHistoryFilesAndBackdatedPayment(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $this->connection->executeStatement("INSERT INTO renters (ownership_id, name) VALUES (2, 'Unknown INN one'), (3, 'Unknown INN two')");
        $meter = $this->connection->fetchOne('SELECT meter_id FROM meter_readings');
        $this->connection->insert('meter_verifications', ['meter_id' => $meter, 'verification_date' => '2026-09-01']);
        $contract = $this->connection->fetchOne('SELECT id FROM contracts');
        $author = $this->connection->fetchOne("INSERT INTO users (email, password, roles, blocked, active, createdat) VALUES ('payment-draft@test.example', '', '[]', false, false, CURRENT_TIMESTAMP) RETURNING id");
        $this->connection->insert('contract_payments', ['contract_id' => $contract, 'created_by_id' => $author, 'payment_date' => '2026-11-01', 'amount' => '123.45']);
        $this->connection->insert('contract_files', ['contract_id' => $contract, 'original_name' => 'file.any', 'storage_name' => 'private-random-key', 'mime_type' => 'application/octet-stream', 'size_bytes' => 20000000]);
        self::assertSame([2, 1, ['2026-11-01', '123.45', $author], 20000000], [
            $this->connection->fetchOne('SELECT COUNT(*) FROM renters WHERE inn IS NULL'),
            $this->connection->fetchOne('SELECT COUNT(*) FROM meter_verifications'),
            $this->connection->fetchNumeric('SELECT payment_date, amount, created_by_id FROM contract_payments'),
            $this->connection->fetchOne('SELECT size_bytes FROM contract_files'),
        ]);
    }

    public function testLatestAccrualCorrectionReplacesAmountIncludingZero(): void
    {
        $this->migrate('DomainMigrations\\Version20261120130000');
        $this->fixtures();
        $contract = $this->connection->fetchOne('SELECT id FROM contracts');
        $author = $this->connection->fetchOne("INSERT INTO users (email, password, roles, blocked, active, createdat) VALUES ('accrual@test.example', '', '[]', false, false, CURRENT_TIMESTAMP) RETURNING id");
        $accrual = $this->connection->fetchOne("INSERT INTO contract_accruals (contract_id, period_month, period_start, period_end, monthly_payment, amount) VALUES (?, '2026-11-01', '2026-11-19', '2026-11-30', 25000.05, 10000.02) RETURNING id", [$contract]);
        foreach (['5000.01', '0'] as $amount) {
            $this->connection->insert('contract_accruals_correction', ['contract_accrual_id' => $accrual, 'period_start' => '2026-11-19', 'period_end' => '2026-11-30', 'monthly_payment' => '25000.05', 'amount' => $amount, 'created_by_id' => $author, 'created_at' => '2026-11-20 12:00:00']);
        }
        self::assertSame(['10000.02', '0'], $this->connection->fetchNumeric('SELECT a.amount, (SELECT c.amount FROM contract_accruals_correction c WHERE c.contract_accrual_id = a.id ORDER BY c.created_at DESC, c.id DESC LIMIT 1) FROM contract_accruals a'));
    }

    private function migrate(string $version): void
    {
        // A fresh factory is needed for each direction because executed migration instances are frozen.
        $factory = DependencyFactory::fromConnection(new ConfigurationArray([
            'migrations_paths' => ['DomainMigrations' => __DIR__.'/../../migrations/domain'],
            'table_storage' => ['table_name' => 'domain_test_migration_versions'],
        ]), new ExistingConnection($this->connection), new NullLogger());
        $factory->getMetadataStorage()->ensureInitialized();
        $factory->getMigrator()->migrate($factory->getMigrationPlanCalculator()->getPlanUntilVersion(new Version($version)), new MigratorConfiguration());
    }

    private function fixtures(): void
    {
        $landlord = $this->connection->fetchOne("INSERT INTO land_lords (name) VALUES ('Арендодатель') RETURNING id");
        $object = $this->connection->fetchOne("INSERT INTO objects (land_lord_id, square, name, address, reading_day) VALUES (?, 1000.25, 'Объект', 'Адрес', 25) RETURNING id", [$landlord]);
        $space = $this->connection->fetchOne("INSERT INTO spaces (object_id, name, square) VALUES (?, 'Выделенная площадь', 100.25) RETURNING id", [$object]);
        $renter = $this->connection->fetchOne("INSERT INTO renters (ownership_id, name, inn) VALUES (1, 'Арендатор', '0012345678') RETURNING id");
        $this->connection->insert('renter_contacts', ['renter_id' => $renter, 'name' => 'Анна', 'last_name' => 'Тестовая', 'phone' => '+70000000000', 'email' => 'contact@test.example']);
        $this->connection->insert('contracts', ['contract_type_id' => 1, 'land_lord_id' => $landlord, 'space_id' => $space, 'renter_id' => $renter, 'security_payment' => '50000.10', 'expiry_date' => '2027-11-19', 'contract_date' => '2026-11-19', 'contract_num' => '0001-А', 'monthly_payment' => '25000.05', 'increase_date' => '2027-05-19', 'increase_percent' => '5.5']);
        $unity = $this->connection->fetchOne("INSERT INTO unities (name, use_meter) VALUES ('Электричество', true) RETURNING id");
        $provider = $this->connection->fetchOne("INSERT INTO unity_providers (unity_id, name) VALUES (?, 'Поставщик') RETURNING id", [$unity]);
        $this->connection->insert('object_unity_providers', ['unity_provider_id' => $provider, 'object_id' => $object]);
        $meter = $this->connection->fetchOne("INSERT INTO meters (utility_id, create_date, start_read, verification_date, verification_interval_days) VALUES (?, '2026-01-01', 120, '2026-01-01', 365) RETURNING id", [$unity]);
        $objectMeter = $this->connection->fetchOne("INSERT INTO meters (utility_id, create_date, start_read, verification_interval_days) VALUES (?, '2026-01-01', 0, 365) RETURNING id", [$unity]);
        $this->connection->insert('object_meters', ['object_id' => $object, 'meter_id' => $objectMeter]);
        $this->connection->insert('space_meters', ['space_id' => $space, 'meter_id' => $meter]);
        $this->connection->insert('meter_readings', ['meter_id' => $meter, 'meter_reading_date' => '2026-11-19 12:30:00', 'meter_read' => 145]);
    }
}
