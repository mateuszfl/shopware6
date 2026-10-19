<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Database\DatabaseSchema;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Symfony\Component\Clock\MockClock;

require dirname(__DIR__, 6) . '/vendor/autoload.php';
$connection = FrameworkSqlFixture::connect();
$pid = $connection->fetchOne((new DatabaseSchema($connection))->isPostgreSQL() ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
echo 'started:' . $pid . "\n";
flush();
$storage = new MySQLIncrementer($connection, new MockClock('2026-10-18 12:00:00 UTC'));
$storage->setPool('concurrent');
$storage->increment('cluster', 'key');
echo "incremented\n";
