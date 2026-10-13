<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Tests\Integration\Core\System\Contract\_fixtures\SystemSqlFixture;
use Symfony\Component\Clock\MockClock;

require dirname(__DIR__, 6) . '/vendor/autoload.php';
$connection = SystemSqlFixture::connect();
$pid = $connection->fetchOne((new DatabaseSchema($connection))->isPostgreSQL() ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
echo 'started:' . $pid . "\n";
flush();
$value = (new IncrementSqlStorage($connection, new MockClock('2026-10-12')))->reserve(['id' => $argv[1], 'pattern' => '{n}', 'start' => 100]);
echo 'reserved:' . $value . "\n";
