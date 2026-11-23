<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1587039363AddImportExportLabelField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587039363;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE "import_export_profile" ALTER COLUMN "name" TYPE VARCHAR(255), ALTER COLUMN "name" DROP NOT NULL;');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS "import_export_profile_translation" (
                "import_export_profile_id" BYTEA CHECK (octet_length("import_export_profile_id") = 16) NOT NULL,
                "language_id" BYTEA CHECK (octet_length("language_id") = 16) NOT NULL,
                "label" VARCHAR(255) COLLATE "sw_type_ci" NULL,
                "created_at" TIMESTAMP(3) WITHOUT TIME ZONE NOT NULL,
                "updated_at" TIMESTAMP(3) WITHOUT TIME ZONE NULL,
                PRIMARY KEY ("import_export_profile_id", "language_id"),
                CONSTRAINT "fk.import_export_profile_translation.import_export_profile_id" FOREIGN KEY ("import_export_profile_id")
                    REFERENCES "import_export_profile" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT "fk.import_export_profile_translation.language_id" FOREIGN KEY ("language_id")
                    REFERENCES "language" ("id") ON DELETE CASCADE ON UPDATE CASCADE
            );
        ');
        $translationsPresent = $connection->fetchOne('SELECT 1 FROM "import_export_profile_translation" LIMIT 1;');
        if ($translationsPresent !== false) {
            return;
        }
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = \'en-GB\';
        ');
        $germanLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = \'de-DE\';
        ');
        if (\is_resource($englishLanguageId)) {
            $englishLanguageId = stream_get_contents($englishLanguageId);
        }
        if (\is_resource($germanLanguageId)) {
            $germanLanguageId = stream_get_contents($germanLanguageId);
        }
        $insertNamesAsLabelsStatement = $connection->prepare('
            INSERT INTO "import_export_profile_translation" ("import_export_profile_id", "language_id", "label", "created_at")
            SELECT "id", :languageId, "name", NOW()
            FROM "import_export_profile";
        ');
        $insertGermanLabelsStatement = $connection->prepare('
            INSERT INTO "import_export_profile_translation" ("import_export_profile_id", "language_id", "label", "created_at")
            SELECT "id", :languageId,
                CASE "name"
                    WHEN \'Default category\' THEN \'Standardprofil Kategorie\'
                    WHEN \'Default media\' THEN \'Standardprofil Medien\'
                    WHEN \'Default variant configuration settings\' THEN \'Standardprofil Variantenkonfiguration\'
                    WHEN \'Default newsletter recipient\' THEN \'Standardprofil Newsletter-Empfänger\'
                    WHEN \'Default properties\' THEN \'Standardprofil Eigenschaften\'
                    WHEN \'Default product\' THEN \'Standardprofil Produkt\'
                    ELSE "name"
                END,
                NOW()
            FROM "import_export_profile";
        ');
        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            StatementHelper::executeStatement($insertNamesAsLabelsStatement, ['languageId' => $defaultLanguageId], ['languageId' => ParameterType::BINARY]);
        }
        if ($englishLanguageId) {
            StatementHelper::executeStatement($insertNamesAsLabelsStatement, ['languageId' => $englishLanguageId], ['languageId' => ParameterType::BINARY]);
        }
        if ($germanLanguageId) {
            StatementHelper::executeStatement($insertGermanLabelsStatement, ['languageId' => $germanLanguageId], ['languageId' => ParameterType::BINARY]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
