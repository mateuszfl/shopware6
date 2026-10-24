<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Installer\_fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * Creates and drops only databases owned by this test invocation. Never boots the kernel.
 *
 * @internal
 */
#[Package('framework')]
abstract class InstallerDatabaseTestCase extends TestCase
{
    protected Connection $connection;

    protected Connection $adminConnection;

    protected SetupDatabaseAdapter $adapter;

    protected string $database;

    protected function setUp(): void
    {
        $information = DatabaseConnectionInformation::fromEnv();
        $this->adminConnection = DatabaseConnectionFactory::createConnection($information, true);
        $this->adapter = new SetupDatabaseAdapter();
        $this->database = 'sw_022_' . bin2hex(random_bytes(6));
        $this->adapter->createDatabase($this->adminConnection, $this->database);
        $information->setDatabaseName($this->database);
        $this->connection = DatabaseConnectionFactory::createConnection($information);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
        if (isset($this->adminConnection, $this->database)) {
            // PHPUnit may retain an exception whose trace still owns the native PDO connection.
            if ($this->adminConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->adminConnection->executeStatement('DROP DATABASE ' . $this->adminConnection->quoteSingleIdentifier($this->database) . ' WITH (FORCE)');
            } else {
                $this->adapter->dropDatabase($this->adminConnection, $this->database);
            }
            $this->adminConnection->close();
        }
    }
}
