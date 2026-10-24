<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Struct;

use Pdo\Mysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseConnectionInformation::class)]
class DatabaseConnectionInformationTest extends TestCase
{
    use EnvTestBehaviour;

    public function testValidInformation(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertNull($info->getSslCaPath());
        static::assertNull($info->getSslCertPath());
        static::assertNull($info->getSslCertKeyPath());
        static::assertNull($info->getSslDontVerifyServerCert());

        static::assertFalse($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'host' => 'localhost',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
            'dbname' => 'shopware',
            'user' => 'root',
            'password' => 'root',
        ], $info->toDBALParameters());

        static::assertSame([
            'host' => 'localhost',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
            'user' => 'root',
            'password' => 'root',
        ], $info->toDBALParameters(true));
    }

    public function testPostgreSQLEnvironmentAndMaintenanceParameters(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgresql://shopware:p%40ss@localhost/shopware_pg',
            'DATABASE_SSL_CA' => '/certs/ca.crt',
            'DATABASE_SSL_CERT' => '/certs/client.crt',
            'DATABASE_SSL_KEY' => '/certs/client.key',
            'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => '',
        ]);

        $info = DatabaseConnectionInformation::fromEnv();
        $params = $info->toDBALParameters();

        static::assertSame('pdo_pgsql', $info->getDriver());
        static::assertSame(5432, $info->getPort());
        static::assertSame('p@ss', $info->getPassword());
        static::assertSame('postgresql://shopware:p%40ss@localhost:5432/shopware_pg', $info->asDsn());
        static::assertSame('pdo_pgsql', $params['driver'] ?? null);
        static::assertSame('utf8', $params['charset'] ?? null);
        static::assertSame('/certs/ca.crt', $params['sslrootcert'] ?? null);
        static::assertSame('/certs/client.crt', $params['sslcert'] ?? null);
        static::assertSame('/certs/client.key', $params['sslkey'] ?? null);
        static::assertSame('verify-full', $params['sslmode'] ?? null);
        static::assertSame('postgres', $info->toDBALParameters(true)['dbname'] ?? null);
        static::assertArrayNotHasKey(Mysql::ATTR_SSL_CA, $params['driverOptions'] ?? []);
    }

    public function testPostgreSQLCertificateVerificationCanBeDisabled(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgresql://shopware:shopware@localhost/shopware_pg',
            'DATABASE_SSL_CA' => '/certs/ca.crt',
            'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => true,
        ]);

        static::assertSame('require', DatabaseConnectionInformation::fromEnv()->toDBALParameters()['sslmode'] ?? null);
    }

    public function testWithAdvancedSettings(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
            'sslCaPath' => '/ca-path',
            'sslCertPath' => '/cert-path',
            'sslCertKeyPath' => '/cert-key-path',
            'sslDontVerifyServerCert' => true,
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertSame('/ca-path', $info->getSslCaPath());
        static::assertSame('/cert-path', $info->getSslCertPath());
        static::assertSame('/cert-key-path', $info->getSslCertKeyPath());
        static::assertTrue($info->getSslDontVerifyServerCert());

        static::assertTrue($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'host' => 'localhost',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                Mysql::ATTR_SSL_CA => '/ca-path',
                Mysql::ATTR_SSL_CERT => '/cert-path',
                Mysql::ATTR_SSL_KEY => '/cert-key-path',
            ] + self::sslVerifyServerCertOption(),
            'dbname' => 'shopware',
            'user' => 'root',
            'password' => 'root',
        ], $info->toDBALParameters());
    }

    public function testAssignWithRequestStringValues(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => '3307',
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
            'sslDontVerifyServerCert' => 'on',
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3307, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertNull($info->getSslCaPath());
        static::assertNull($info->getSslCertPath());
        static::assertNull($info->getSslCertKeyPath());
        static::assertTrue($info->getSslDontVerifyServerCert());

        static::assertTrue($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'host' => 'localhost',
            'port' => 3307,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ] + self::sslVerifyServerCertOption(),
            'dbname' => 'shopware',
            'user' => 'root',
            'password' => 'root',
        ], $info->toDBALParameters());

        static::assertSame([
            'host' => 'localhost',
            'port' => 3307,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ] + self::sslVerifyServerCertOption(),
            'user' => 'root',
            'password' => 'root',
        ], $info->toDBALParameters(true));
    }

    public function testInvalid(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => '',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
        ]);

        static::assertSame('', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());

        $this->expectExceptionObject(MaintenanceException::dbConnectionParameterMissing('hostname'));
        $info->validate();
    }

    #[DataProvider('dsnProvider')]
    public function testAsDsn(DatabaseConnectionInformation $connectionInformation, bool $withoutDB, string $expectedDsn): void
    {
        $dsn = $connectionInformation->asDsn($withoutDB);

        static::assertSame($expectedDsn, $dsn);
    }

    public function testPostgreSqlDsnPreservesTlsQueryIpv6AndEncodedCredentials(): void
    {
        $dsn = 'postgresql://user%40name:secret%3F@[::1]:5433/shop%20name?sslmode=verify-full&sslrootcert=%2Fca.pem&sslcert=%2Fclient.pem&sslkey=%2Fkey.pem&application_name=installer';
        $information = DatabaseConnectionInformation::fromDsn($dsn);
        static::assertSame($dsn, $information->asDsn());
        $parameters = $information->toDBALParameters();
        static::assertSame('::1', $parameters['host'] ?? null);
        static::assertSame('user@name', $parameters['user'] ?? null);
        static::assertSame('secret?', $parameters['password'] ?? null);
        static::assertSame('shop name', $parameters['dbname'] ?? null);
        static::assertSame('pdo_pgsql', $parameters['driver'] ?? null);
        static::assertSame('verify-full', $parameters['sslmode'] ?? null);
        static::assertSame('/ca.pem', $parameters['sslrootcert'] ?? null);
        static::assertSame('/client.pem', $parameters['sslcert'] ?? null);
        static::assertSame('/key.pem', $parameters['sslkey'] ?? null);
        static::assertSame('installer', $parameters['application_name'] ?? null);
        static::assertSame('postgres', $information->toDBALParameters(true)['dbname'] ?? null);
    }

    public function testEnvironmentTlsOverridesDsnOptionsWithoutLosingOtherOptions(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'postgresql://user@host/db?sslmode=disable&application_name=installer',
            'DATABASE_SSL_CA' => '/trusted-ca.pem',
            'DATABASE_SSL_CERT' => '/client.pem',
            'DATABASE_SSL_KEY' => '/key.pem',
            'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => 'false',
        ]);
        $information = DatabaseConnectionInformation::fromEnv();
        $parameters = $information->toDBALParameters();
        static::assertSame(5432, $parameters['port'] ?? null);
        static::assertSame('verify-full', $parameters['sslmode'] ?? null);
        static::assertSame('/trusted-ca.pem', $parameters['sslrootcert'] ?? null);
        static::assertSame('installer', $parameters['application_name'] ?? null);
        static::assertFalse($information->getSslDontVerifyServerCert());
        static::assertArrayNotHasKey(Mysql::ATTR_SSL_CA, $parameters['driverOptions'] ?? []);
    }

    public function testPostgreSqlFormDefaultsAndExplicitInsecureTls(): void
    {
        $information = (new DatabaseConnectionInformation())->assign([
            'driver' => 'pdo_pgsql', 'hostname' => 'localhost', 'username' => 'user', 'databaseName' => 'shop',
            'sslDontVerifyServerCert' => '1',
        ]);
        static::assertSame(5432, $information->getPort());
        static::assertSame('require', $information->toDBALParameters()['sslmode'] ?? null);
        static::assertSame('postgresql://user@localhost:5432/shop', $information->asDsn());
        $information->validate();
    }

    public function testFormQueryOptionsCannotOverrideConnectionIdentityOrWrapper(): void
    {
        $information = (new DatabaseConnectionInformation())->assign([
            'driver' => 'pdo_pgsql', 'hostname' => 'localhost', 'username' => 'user', 'databaseName' => 'shop',
            'dsnParameters' => ['dbname' => 'other', 'wrapperClass' => 'OtherConnection', 'sslmode' => 'require'],
        ]);
        static::assertSame(['sslmode' => 'require'], $information->getDsnParameters());
        static::assertSame('shop', $information->toDBALParameters()['dbname'] ?? null);
        static::assertArrayNotHasKey('wrapperClass', $information->toDBALParameters());
    }

    public function testUnsupportedDriverIsRejected(): void
    {
        $this->expectExceptionObject(MaintenanceException::unsupportedDatabaseDriver('pdo_sqlite'));
        (new DatabaseConnectionInformation())->assign(['driver' => 'pdo_sqlite']);
    }

    public static function dsnProvider(): \Generator
    {
        yield 'with database' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root:root@localhost:3306/shopware',
        ];

        yield 'without database' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
            true,
            'mysql://root:root@localhost:3306',
        ];

        yield 'without password' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root@localhost:3306/shopware',
        ];

        yield 'without password and user' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://localhost:3306/shopware',
        ];

        yield 'special chars in password' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'mysql',
                'port' => 3306,
                'username' => 'root',
                'password' => 'ultra?secure#',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root:ultra%3Fsecure%23@mysql:3306/shopware',
        ];
    }

    /**
     * @param array<string, string|bool> $env
     */
    #[DataProvider('validEnvProvider')]
    public function testFromEnv(array $env, DatabaseConnectionInformation $expected): void
    {
        $this->setEnvVars($env);

        $info = DatabaseConnectionInformation::fromEnv();

        static::assertEquals($expected->getVars(), $info->getVars());
    }

    public static function validEnvProvider(): \Generator
    {
        yield 'only database' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'advanced settings' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306/shopware',
                'DATABASE_SSL_CA' => '/ca-path',
                'DATABASE_SSL_CERT' => '/cert-path',
                'DATABASE_SSL_KEY' => '/cert-key-path',
                'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => true,
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
                'sslCaPath' => '/ca-path',
                'sslCertPath' => '/cert-path',
                'sslCertKeyPath' => '/cert-key-path',
                'sslDontVerifyServerCert' => true,
            ]),
        ];

        yield 'without password' => [
            [
                'DATABASE_URL' => 'mysql://root@localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'without username and password' => [
            [
                'DATABASE_URL' => 'mysql://localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'without port' => [
            [
                'DATABASE_URL' => 'mysql://localhost/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'special chars in password' => [
            [
                'DATABASE_URL' => 'mysql://root:ultra%3Fsecure%23@mysql:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'mysql',
                'port' => 3306,
                'username' => 'root',
                'password' => 'ultra?secure#',
                'databaseName' => 'shopware',
            ]),
        ];
    }

    /**
     * @param array<string, string|bool> $env
     */
    #[DataProvider('invalidEnvProvider')]
    public function testFromEnvWithInvalidEnv(array $env, MaintenanceException $expectedException): void
    {
        $this->setEnvVars($env);

        $this->expectExceptionObject($expectedException);
        DatabaseConnectionInformation::fromEnv();
    }

    public static function invalidEnvProvider(): \Generator
    {
        yield 'Database url not set' => [
            [
                'DATABASE_URL' => '',
            ],
            MaintenanceException::environmentVariableNotDefined('DATABASE_URL'),
        ];

        yield 'invalid database url' => [
            [
                'DATABASE_URL' => 'invalid',
            ],
            MaintenanceException::environmentVariableNotValid('DATABASE_URL', 'invalid', 'Not a valid DSN'),
        ];

        yield 'Database name not set' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306',
            ],
            MaintenanceException::environmentVariableNotValid('DATABASE_URL', 'mysql://root:root@localhost:3306', 'Not a valid DSN'),
        ];
    }

    /**
     * The constant only exists when PDO is built against a MySQL client library that supports it, and the
     * struct only emits the option in that case.
     *
     * @return array<int, false>
     */
    private static function sslVerifyServerCertOption(): array
    {
        if (!\defined('\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            return [];
        }

        return [Mysql::ATTR_SSL_VERIFY_SERVER_CERT => false];
    }
}
