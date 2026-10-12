<?php declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Shopware\Core\Framework\Adapter\Database\DatabaseFunctionInstaller;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$url = getenv('DATABASE_FUNCTION_INSTALL_URL');
if (!\is_string($url) || $url === '') {
    throw new RuntimeException('DATABASE_FUNCTION_INSTALL_URL must identify the primary application database and a maintenance account');
}

$connection = DriverManager::getConnection((new DsnParser([
    'mysql' => 'pdo_mysql',
    'mariadb' => 'pdo_mysql',
    'postgresql' => 'pdo_pgsql',
    'postgres' => 'pdo_pgsql',
]))->parse($url));

try {
    (new DatabaseFunctionInstaller())->install($connection);
    fwrite(\STDOUT, "Database functions installed and verified.\n");
} finally {
    $connection->close();
}
