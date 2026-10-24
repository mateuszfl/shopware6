<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('framework')]
class BlueGreenDeploymentService
{
    final public const ENV_NAME = 'BLUE_GREEN_DEPLOYMENT';

    public function setEnvironmentVariable(Connection $connection, SessionInterface $session): void
    {
        $value = $this->checkIfMayCreateTrigger($connection);

        $_SERVER[self::ENV_NAME] = $_ENV[self::ENV_NAME] = $value;
        $session->set(self::ENV_NAME, $value);
    }

    private function checkIfMayCreateTrigger(Connection $connection): bool
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            try {
                $connection->executeStatement('CREATE TEMPORARY TABLE sw_blue_green_probe (id INTEGER)');
                $connection->executeStatement('CREATE FUNCTION pg_temp.sw_blue_green_probe() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RETURN NEW; END $$');
                $connection->executeStatement('CREATE TRIGGER sw_blue_green_probe BEFORE UPDATE ON sw_blue_green_probe FOR EACH ROW EXECUTE FUNCTION pg_temp.sw_blue_green_probe()');

                return true;
            } catch (Exception) {
                return false;
            } finally {
                $connection->executeStatement('DROP TABLE IF EXISTS pg_temp.sw_blue_green_probe');
                $connection->executeStatement('DROP FUNCTION IF EXISTS pg_temp.sw_blue_green_probe()');
            }
        }

        try {
            $connection->executeQuery($this->getCreateTableQuery());
            $connection->executeQuery($this->getTriggerQuery());
        } catch (Exception) {
            return false;
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS example');
        }

        return true;
    }

    private function getCreateTableQuery(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS `example` (
              `id` int NOT NULL
            );
SQL;
    }

    private function getTriggerQuery(): string
    {
        return <<<'SQL'
            CREATE TRIGGER example_trigger BEFORE UPDATE ON `example`
                FOR EACH ROW
                BEGIN
                END;
SQL;
    }
}
