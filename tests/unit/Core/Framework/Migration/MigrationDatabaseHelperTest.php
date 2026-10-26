<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationDatabaseHelper;
use Shopware\Core\Framework\Migration\MigrationException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationDatabaseHelper::class)]
class MigrationDatabaseHelperTest extends TestCase
{
    public function testNormalizesByteaStreamAndPreservesBinaryString(): void
    {
        $id = random_bytes(16);
        $stream = fopen('php://memory', 'r+');
        static::assertIsResource($stream);
        fwrite($stream, $id);
        rewind($stream);

        static::assertSame($id, MigrationDatabaseHelper::normalizeBinaryId($stream));
        static::assertSame($id, MigrationDatabaseHelper::normalizeBinaryId($id));

        fclose($stream);
    }

    public function testRejectsValuesThatAreNotBinaryUuids(): void
    {
        $this->expectException(MigrationException::class);

        MigrationDatabaseHelper::normalizeBinaryId('short');
    }
}
