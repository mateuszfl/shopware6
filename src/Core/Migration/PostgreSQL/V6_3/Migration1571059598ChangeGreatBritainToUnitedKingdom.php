<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1571059598ChangeGreatBritainToUnitedKingdom extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571059598;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE country_translation AS translation
            SET name = 'United Kingdom'
            FROM language
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE language.id = translation.language_id
                AND locale.code = 'en-GB'
                AND translation.name = 'Great Britain'
            SQL);

        $connection->executeStatement(<<<'SQL'
            UPDATE country_translation AS translation
            SET name = 'Vereinigtes Königreich'
            FROM language
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE language.id = translation.language_id
                AND locale.code = 'de-DE'
                AND translation.name = 'Großbritannien'
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
