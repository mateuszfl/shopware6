<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1572193798TaxRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572193798;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
        $this->addTaxRuleTypes($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createTables(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tax_rule_type (
                id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_type_id_uuid CHECK (octet_length(id) = 16),
                technical_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                position INT NOT NULL,
                created_at TIMESTAMP(3) NOT NULL,
                updated_at TIMESTAMP(3) NULL,
                PRIMARY KEY (id),
                CONSTRAINT "uniq.technical_name" UNIQUE (technical_name)
            )
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tax_rule_type_translation (
                tax_rule_type_id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_type_translation_tax_rule_type_id_uuid CHECK (octet_length(tax_rule_type_id) = 16),
                language_id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_type_translation_language_id_uuid CHECK (octet_length(language_id) = 16),
                type_name VARCHAR(255) COLLATE sw_type_ci NOT NULL,
                created_at TIMESTAMP(3) NOT NULL,
                updated_at TIMESTAMP(3) NULL,
                PRIMARY KEY (tax_rule_type_id, language_id),
                CONSTRAINT "fk.tax_rule_type_translation.tax_rule_type_id"
                    FOREIGN KEY (tax_rule_type_id) REFERENCES tax_rule_type (id),
                CONSTRAINT "fk.tax_rule_type_translation.language_id"
                    FOREIGN KEY (language_id) REFERENCES language (id)
            )
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tax_rule (
                id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_id_uuid CHECK (octet_length(id) = 16),
                tax_id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_tax_id_uuid CHECK (octet_length(tax_id) = 16),
                tax_rule_type_id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_tax_rule_type_id_uuid CHECK (octet_length(tax_rule_type_id) = 16),
                country_id BYTEA NOT NULL CONSTRAINT chk_sw_tax_rule_country_id_uuid CHECK (octet_length(country_id) = 16),
                tax_rate DOUBLE PRECISION NOT NULL,
                data JSONB NULL,
                created_at TIMESTAMP(3) NOT NULL,
                updated_at TIMESTAMP(3) NULL,
                PRIMARY KEY (id),
                CONSTRAINT "json.tax_rule.data" CHECK (data IS NULL OR jsonb_typeof(data) IS NOT NULL),
                CONSTRAINT "fk.tax_rule.tax_id" FOREIGN KEY (tax_id) REFERENCES tax (id),
                CONSTRAINT "fk.tax_rule.tax_area_rule_type_id" FOREIGN KEY (tax_rule_type_id) REFERENCES tax_rule_type (id),
                CONSTRAINT "fk.tax_rule.country_id" FOREIGN KEY (country_id) REFERENCES country (id)
            )
            SQL);
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_tax_rule_type_translation_language_id ON tax_rule_type_translation (language_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_tax_rule_country_id ON tax_rule (country_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_tax_rule_tax_id ON tax_rule (tax_id)');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS idx_tax_rule_tax_rule_type_id ON tax_rule (tax_rule_type_id)');
    }

    private function addTaxRuleTypes(Connection $connection): void
    {
        $languageIdEn = $this->getLocaleId($connection, 'en-GB');
        $languageIdDe = $this->getLocaleId($connection, 'de-DE');
        $languageSystem = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $dataDe = [
            ZipCodeRuleTypeFilter::TECHNICAL_NAME => 'Postleitzahl',
            ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME => 'Postleitzahl Bereich',
            IndividualStatesRuleTypeFilter::TECHNICAL_NAME => 'Individuelle Bundesländer',
            EntireCountryRuleTypeFilter::TECHNICAL_NAME => 'Gesamte Land',
        ];
        $dataEn = [
            ZipCodeRuleTypeFilter::TECHNICAL_NAME => 'Zip Code',
            ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME => 'Zip Code Range',
            IndividualStatesRuleTypeFilter::TECHNICAL_NAME => 'Individual States',
            EntireCountryRuleTypeFilter::TECHNICAL_NAME => 'Entire Country',
        ];

        foreach (array_keys($dataEn) as $position => $technicalName) {
            $typeId = $connection->fetchOne(
                'SELECT id FROM tax_rule_type WHERE technical_name = :technicalName',
                ['technicalName' => $technicalName]
            );
            if (\is_resource($typeId)) {
                $typeId = stream_get_contents($typeId);
            }
            if (!\is_string($typeId)) {
                $typeId = Uuid::randomBytes();
                $connection->insert(TaxRuleTypeDefinition::ENTITY_NAME, [
                    'id' => $typeId,
                    'technical_name' => $technicalName,
                    'position' => $position,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING]);
            }

            if (!\in_array($languageSystem, [$languageIdDe, $languageIdEn], true)) {
                $this->insertTranslationIfMissing($connection, $typeId, $languageSystem, $dataEn[$technicalName]);
            }
            if ($languageIdEn !== null) {
                $this->insertTranslationIfMissing($connection, $typeId, $languageIdEn, $dataEn[$technicalName]);
            }
            if ($languageIdDe !== null) {
                $this->insertTranslationIfMissing($connection, $typeId, $languageIdDe, $dataDe[$technicalName]);
            }
        }
    }

    private function getLocaleId(Connection $connection, string $code): ?string
    {
        $languageId = $connection->fetchOne(<<<'SQL'
            SELECT language.id
            FROM language
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE locale.code = :code
            SQL, ['code' => $code]);
        if (\is_resource($languageId)) {
            $languageId = stream_get_contents($languageId);
        }

        return \is_string($languageId) ? $languageId : null;
    }

    private function insertTranslationIfMissing(Connection $connection, string $typeId, string $languageId, string $name): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM tax_rule_type_translation WHERE tax_rule_type_id = :typeId AND language_id = :languageId',
            ['typeId' => $typeId, 'languageId' => $languageId],
            ['typeId' => ParameterType::BINARY, 'languageId' => ParameterType::BINARY]
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('tax_rule_type_translation', [
            'tax_rule_type_id' => $typeId,
            'language_id' => $languageId,
            'type_name' => $name,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
    }
}
