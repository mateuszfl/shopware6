<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\LegacySource\Migration1000000000Legacy as Legacy;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001First as First;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000001FirstPg as FirstPg;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000002Second as Second;
use Shopware\Tests\Integration\Core\Framework\Migration\_fixtures\PlatformSource\Migration1000000002SecondPg as SecondPg;

/**
 * Uses an empty disposable database; never boots the kernel or imports the historical schema.
 *
 * @internal
 */
#[Package('framework')]
class MigrationPlatformContractTest extends TestCase
{
    private Connection $connection;

    private MigrationVariantRegistry $registry;

    private MigrationCollection $collection;

    /**
     * @var list<string>
     */
    private array $ownedTables = [];

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['migration']), 'Use a separate empty contract database.');
        static::assertFalse($this->connection->createSchemaManager()->tablesExist(['migration_contract_event']));
        $update = $this->connection->getDatabasePlatform()->quoteSingleIdentifier('update');
        $this->connection->executeStatement('CREATE TABLE migration (class VARCHAR(255) PRIMARY KEY, creation_timestamp INTEGER NOT NULL, ' . $update . ' TIMESTAMP(6) NULL, update_destructive TIMESTAMP(6) NULL, message TEXT NULL)');
        $this->ownedTables[] = 'migration';
        $this->connection->executeStatement('CREATE TABLE migration_contract_event (step VARCHAR(64) PRIMARY KEY, engine VARCHAR(32) NOT NULL)');
        $this->ownedTables[] = 'migration_contract_event';
        $this->registry = new MigrationVariantRegistry([
            [First::class, 1000000001, FirstPg::class],
            [Second::class, 1000000002, SecondPg::class],
        ]);
        $this->collection = $this->createCollection($this->registry);
        Second::$fail = false;
        Second::$typeError = false;
        Second::$commitInside = false;
    }

    protected function tearDown(): void
    {
        Second::$fail = false;
        Second::$typeError = false;
        Second::$commitInside = false;
        if ($this->ownedTables !== []) {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            foreach (array_reverse($this->ownedTables) as $table) {
                $this->connection->executeStatement('DROP TABLE ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }
        }
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    public function testDiscoverySyncRoutingHistoryAndRepeatedRuns(): void
    {
        static::assertSame([First::class, Second::class], array_keys($this->collection->getMigrationSteps()));
        $this->collection->sync();
        $this->collection->sync();
        static::assertSame(2, $this->collection->getTotalMigrationCount());
        static::assertSame([First::class], $this->collection->migrateInPlace(until: 1000000001));
        static::assertSame([Second::class], $this->collection->migrateInPlace(limit: 1));
        static::assertSame([], $this->collection->migrateInPlace());
        static::assertSame([First::class, Second::class], $this->collection->migrateDestructiveInPlace());
        static::assertSame([], $this->collection->migrateDestructiveInPlace());
        $expectedEngine = $this->isPostgreSQL() ? 'postgresql' : 'mysql';
        static::assertSame([$expectedEngine], $this->connection->fetchFirstColumn('SELECT DISTINCT engine FROM migration_contract_event'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        foreach ($this->connection->fetchAllAssociative('SELECT * FROM migration ORDER BY creation_timestamp') as $row) {
            static::assertNotNull($row['update']);
            static::assertNotNull($row['update_destructive']);
            static::assertNull($row['message']);
        }
        static::assertNotNull((new MigrationInfo($this->connection))->getFirstMigrationDate());
    }

    public function testNamespaceMatchingDoesNotExecuteNestedOrSiblingSources(): void
    {
        $this->collection->sync();
        foreach ([First::class . '\\Nested', str_replace('PlatformSource', 'PlatformSourceOther', First::class)] as $class) {
            $this->connection->insert('migration', ['class' => $class, 'creation_timestamp' => 1000000000]);
        }
        static::assertSame(2, $this->collection->getTotalMigrationCount());
        static::assertSame([First::class, Second::class], $this->collection->getExecutableMigrations());
        static::assertSame([First::class, Second::class], $this->collection->migrateInPlace());
    }

    public function testMissingVariantIsRejectedBeforeFirstSelectedStepRuns(): void
    {
        $this->requirePostgreSQL();
        // Sync with the complete registry: the counterparts must never enter history.
        $this->collection->sync();
        $incomplete = $this->createCollection(new MigrationVariantRegistry([[First::class, 1000000001, FirstPg::class]]));
        try {
            $incomplete->migrateInPlace();
            static::fail('Missing variant must stop the entire selected run.');
        } catch (\RuntimeException $e) {
            static::assertStringContainsString('No PostgreSQL migration variant for ' . Second::class, $e->getMessage());
        }
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        static::assertSame([First::class, Second::class], $this->collection->getExecutableMigrations());
        // The limit constrains preflight as well as execution.
        static::assertSame([First::class], $incomplete->migrateInPlace(limit: 1));
    }

    public function testHistoryTimestampMismatchStopsRunBeforeExecution(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $this->connection->update('migration', ['creation_timestamp' => 1000000003], ['class' => Second::class]);
        try {
            $this->collection->migrateInPlace();
            static::fail('History identity must match the registered timestamp.');
        } catch (\RuntimeException $e) {
            static::assertStringContainsString('timestamp mismatch', $e->getMessage());
        }
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
    }

    public function testSqlFailureRecordsErrorAndResumesAfterRollback(): void
    {
        $this->collection->sync();
        Second::$fail = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('The synthetic migration must fail.');
        } catch (Exception $e) {
            static::assertStringContainsString('migration_contract_missing_table', $e->getMessage());
        }
        static::assertFalse($this->connection->isTransactionActive());
        static::assertSame([Second::class], $this->collection->getExecutableMigrations());
        $failed = $this->connection->fetchAssociative('SELECT * FROM migration WHERE class = ?', [Second::class]);
        static::assertIsArray($failed);
        static::assertNull($failed['update']);
        static::assertStringContainsString('migration_contract_missing_table', $failed['message']);
        static::assertSame($this->isPostgreSQL() ? 1 : 2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        Second::$fail = false;
        static::assertSame([Second::class], $this->collection->migrateInPlace());
        static::assertNull($this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
        static::assertSame([], $this->collection->migrateInPlace());
    }

    public function testDestructiveFailureCanResumeWithoutRepeatingUpdate(): void
    {
        $this->collection->sync();
        $this->collection->migrateInPlace();
        Second::$fail = true;
        try {
            $this->collection->migrateDestructiveInPlace();
            static::fail('The destructive step must fail.');
        } catch (Exception) {
        }
        static::assertSame([Second::class], $this->collection->getExecutableDestructiveMigrations());
        static::assertSame([], $this->collection->getExecutableMigrations());
        static::assertFalse($this->connection->isTransactionActive());
        Second::$fail = false;
        static::assertSame([Second::class], $this->collection->migrateDestructiveInPlace());
        static::assertNull($this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
    }

    public function testThrowableRollsBackAndPreservesUtf8Diagnostic(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        Second::$typeError = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('The synthetic TypeError must be propagated.');
        } catch (\TypeError $e) {
            static::assertSame('migration contract type error: żółć', $e->getMessage());
        }
        static::assertSame('migration contract type error: żółć', $this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
        static::assertSame(['first'], $this->connection->fetchFirstColumn('SELECT step FROM migration_contract_event'));
        Second::$typeError = false;
        Second::$commitInside = false;
        static::assertSame([Second::class], $this->collection->migrateInPlace());
    }

    public function testCallerTransactionIsNeverRolledBack(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $this->connection->beginTransaction();
        $this->connection->insert('migration_contract_event', ['step' => 'caller', 'engine' => 'caller']);
        try {
            $this->collection->migrateInPlace();
            static::fail('Outer transactions must be rejected before execution.');
        } catch (MigrationException $e) {
            static::assertStringContainsString('without an active transaction', $e->getMessage());
        }
        static::assertTrue($this->connection->isTransactionActive());
        static::assertSame(['caller'], $this->connection->fetchFirstColumn('SELECT step FROM migration_contract_event'));
        $this->connection->rollBack();
    }

    public function testPluginSourceUsesSameRegistryAndHistoryAcrossLifecycleCollections(): void
    {
        $source = $this->source('ExamplePlugin');
        $loader = new MigrationCollectionLoader($this->connection, new MigrationRuntime($this->connection, new NullLogger(), $this->registry), new NullLogger());
        $loader->addSource($source);
        $install = $loader->collect('ExamplePlugin');
        $install->sync();
        static::assertSame([First::class, Second::class], $install->migrateInPlace());
        $activate = $loader->collect('ExamplePlugin');
        $activate->sync();
        static::assertSame([], $activate->migrateInPlace());
        // Plugin::removeMigrations removes the logical namespace, ready for reinstall.
        $this->connection->executeStatement('DELETE FROM migration');
        $this->connection->executeStatement('DELETE FROM migration_contract_event');
        $loader->collect('ExamplePlugin')->sync();
        static::assertSame([First::class, Second::class], $loader->collect('ExamplePlugin')->migrateInPlace());
    }

    public function testVersionedSourceRunsBeforeOlderLegacyTimestampAndLimitsRespectOrder(): void
    {
        $this->registry->register(Legacy::class, 1000000000, Legacy::class);
        $legacy = new MigrationSource('core', [__DIR__ . '/_fixtures/LegacySource' => __NAMESPACE__ . '\\_fixtures\\LegacySource']);
        $source = new MigrationSource('combined', [$this->source('core.V6_3'), $legacy]);
        $runtime = new MigrationRuntime($this->connection, new NullLogger(), $this->registry);
        $collection = new MigrationCollection($source, $runtime, $this->connection, new NullLogger());
        $collection->sync();

        static::assertSame([First::class, Second::class, Legacy::class], $collection->getExecutableMigrations());
        static::assertSame([First::class, Legacy::class], $collection->getExecutableMigrations(until: 1000000001));
        static::assertSame(1, $collection->getTotalMigrationCount(limit: 1));
        static::assertSame([First::class], $collection->migrateInPlace(until: 1000000001, limit: 1));
        static::assertSame([Second::class, Legacy::class], $collection->migrateInPlace());
        static::assertSame([First::class, Second::class, Legacy::class], $collection->migrateDestructiveInPlace());
    }

    public function testEmptyAndUnicodeNamespacesAreMatchedWithoutRegexEncodingAssumptions(): void
    {
        $namespace = __NAMESPACE__ . '\\Mígracje_百分!';
        $class = $namespace . '\\MigrationŻółć';
        $this->connection->insert('migration', ['class' => $class, 'creation_timestamp' => 1000000000]);
        $runtime = new MigrationRuntime($this->connection, new NullLogger(), $this->registry);
        static::assertSame([], $runtime->getExecutableMigrations(new MigrationSource('empty')));
        static::assertSame([$class], $runtime->getExecutableMigrations(new MigrationSource('unicode', [__DIR__ => $namespace])));
    }

    public function testPreKernelInstallerFactoryUsesStrictRoutingAndIncludesV68(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        $loader = (new MigrationCollectionFactory(\dirname(__DIR__, 5)))->getMigrationCollectionLoader($this->connection);
        static::assertArrayHasKey('core.V6_8', $loader->collectAll());
        $loader->addSource($this->source('installer-contract'));
        $this->expectExceptionObject(MigrationException::migrationError('No PostgreSQL migration variant for ' . First::class . ' (timestamp 1000000001).'));
        $loader->collect('installer-contract')->migrateInPlace();
    }

    public function testMigrationCommittingItsOwnBoundaryIsRejectedWithoutClaimingSuccess(): void
    {
        $this->requirePostgreSQL();
        $this->collection->sync();
        Second::$commitInside = true;
        try {
            $this->collection->migrateInPlace();
            static::fail('Variants must not commit the runtime transaction.');
        } catch (MigrationException $e) {
            static::assertStringContainsString('changed its transaction boundary', $e->getMessage());
        }
        static::assertSame([Second::class], $this->collection->getExecutableMigrations());
        // An explicit COMMIT cannot be undone. Do not claim atomicity for unsupported variants.
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM migration_contract_event'));
        static::assertStringContainsString('changed its transaction boundary', $this->connection->fetchOne('SELECT message FROM migration WHERE class = ?', [Second::class]));
    }

    public function testDestructiveModesSelectSourceGroupsBeforeRouting(): void
    {
        $this->registry->register(Legacy::class, 1000000000, Legacy::class);
        $loader = new MigrationCollectionLoader($this->connection, new MigrationRuntime($this->connection, new NullLogger(), $this->registry), new NullLogger(), [
            new MigrationSource('core'),
            $this->source('core.V6_3'),
            new MigrationSource('core.V6_4', [__DIR__ . '/_fixtures/LegacySource' => __NAMESPACE__ . '\\_fixtures\\LegacySource']),
            new MigrationSource('core.V6_5'),
        ]);
        $all = $loader->collectAllForVersion('6.5.2');
        $all->sync();
        $all->migrateInPlace();
        $safe = $loader->collectAllForVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_SAFE);
        static::assertSame([First::class, Second::class], $safe->getExecutableDestructiveMigrations());
        static::assertSame([First::class, Second::class], $safe->migrateDestructiveInPlace());
        $blueGreen = $loader->collectAllForVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN);
        static::assertSame([Legacy::class], $blueGreen->getExecutableDestructiveMigrations());
        static::assertSame([Legacy::class], $blueGreen->migrateDestructiveInPlace());
        static::assertSame([], $all->migrateDestructiveInPlace());
    }

    private function createCollection(MigrationVariantRegistry $registry): MigrationCollection
    {
        return new MigrationCollection($this->source(), new MigrationRuntime($this->connection, new NullLogger(), $registry), $this->connection, new NullLogger());
    }

    private function source(string $name = 'contract'): MigrationSource
    {
        return new MigrationSource($name, [__DIR__ . '/_fixtures/PlatformSource' => __NAMESPACE__ . '\\_fixtures\\PlatformSource']);
    }

    private function isPostgreSQL(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    private function requirePostgreSQL(): void
    {
        if (!$this->isPostgreSQL()) {
            static::markTestSkipped('PostgreSQL transaction and strict routing contract.');
        }
    }
}
