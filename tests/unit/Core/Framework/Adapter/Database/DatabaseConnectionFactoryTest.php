<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabaseConnectionFactory;
use Shopware\Core\Framework\Adapter\Database\DatabaseDialectException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseConnectionFactory::class)]
class DatabaseConnectionFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testPostgreSQLReplicasInheritSessionAndTlsSettings(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgresql://user:pass@primary:5432/shopware?keepReplica=1',
            'DATABASE_REPLICA_0_URL' => 'postgresql://user:pass@replica-0:5432/shopware',
            'DATABASE_REPLICA_1_URL' => 'postgresql://user:pass@replica-1:5432/shopware?sslmode=verify-ca',
            'DATABASE_SSL_CA' => '/certs/ca.crt',
            'DATABASE_SSL_CERT' => '/certs/client.crt',
            'DATABASE_SSL_KEY' => '/certs/client.key',
            'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => '',
            'DATABASE_PROTOCOL_COMPRESSION' => '1',
        ]);

        $connection = DatabaseConnectionFactory::create();
        $params = $connection->getParams();

        static::assertFalse($connection->isConnected());
        static::assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        static::assertTrue($params['keepReplica'] ?? null);
        static::assertCount(2, $params['replica'] ?? []);
        static::assertSame('pdo_pgsql', $params['primary']['driver'] ?? null);
        static::assertSame('utf8', $params['primary']['charset'] ?? null);
        static::assertSame('/certs/ca.crt', $params['primary']['sslrootcert'] ?? null);
        static::assertSame('/certs/client.crt', $params['replica'][0]['sslcert'] ?? null);
        static::assertSame('verify-full', $params['replica'][0]['sslmode'] ?? null);
        static::assertSame('verify-ca', $params['replica'][1]['sslmode'] ?? null);
        static::assertArrayNotHasKey(Mysql::ATTR_INIT_COMMAND, $params['primary']['driverOptions'] ?? []);
        static::assertArrayNotHasKey(Mysql::ATTR_COMPRESS, $params['replica'][0]['driverOptions'] ?? []);
    }

    public function testPostgreSQLCanDisableReplicaPinning(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgres://user:pass@primary/shopware?keepReplica=0',
            'DATABASE_REPLICA_0_URL' => 'postgres://user:pass@replica/shopware',
        ]);

        $params = DatabaseConnectionFactory::create()->getParams();

        static::assertFalse($params['keepReplica'] ?? null);
        static::assertSame('pdo_pgsql', $params['replica'][0]['driver'] ?? null);
    }

    public function testPostgreSQLPlaceholderIsRecognizedWithoutConnecting(): void
    {
        $this->setEnvVars(['DATABASE_URL' => DatabaseConnectionFactory::POSTGRESQL_PLACEHOLDER_DATABASE_URL]);

        static::assertTrue(DatabaseConnectionFactory::hasNoDatabaseAvailable());
        static::assertFalse(DatabaseConnectionFactory::create()->isConnected());
    }

    public function testMismatchedReplicaDriverIsRejected(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgresql://user:pass@primary/shopware',
            'DATABASE_REPLICA_0_URL' => 'mysql://user:pass@replica/shopware',
        ]);

        $this->expectExceptionObject(DatabaseDialectException::replicaDriverMismatch());
        DatabaseConnectionFactory::create();
    }
}
