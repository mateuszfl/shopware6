<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1571660203FixOrderDeliveryStateNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1571660203;
    }

    public function update(Connection $connection): void
    {
        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        foreach ($this->getMailTemplatesMapping() as $technicalName => $mailTemplate) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTypeTranslation($connection, $technicalName, $mailTemplate['name'], $defaultLangId);
            }

            $languageSystem = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
            if ($defaultLangId !== $languageSystem) {
                $this->updateMailTemplateTypeTranslation($connection, $technicalName, $mailTemplate['name'], $languageSystem);
            }

            if ($deLangId !== null) {
                $this->updateMailTemplateTypeTranslation($connection, $technicalName, $mailTemplate['nameDe'], $deLangId);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string, array{name: string, nameDe: string}>
     */
    private function getMailTemplatesMapping(): array
    {
        return [
            'state_enter.order_delivery.state.returned_partially' => [
                'name' => 'Enter delivery state: Open',
                'nameDe' => 'Eintritt Lieferstatus: Offen',
            ],
            'state_enter.order_delivery.state.shipped_partially' => [
                'name' => 'Enter delivery state: Shipped (partially)',
                'nameDe' => 'Eintritt Lieferstatus: Teilweise versandt',
            ],
            'state_enter.order_delivery.state.returned' => [
                'name' => 'Enter delivery state: Returned',
                'nameDe' => 'Eintritt Lieferstatus: Retour',
            ],
            'state_enter.order_delivery.state.shipped' => [
                'name' => 'Enter delivery state: Shipped',
                'nameDe' => 'Eintritt Lieferstatus: Versandt',
            ],
            'state_enter.order_delivery.state.cancelled' => [
                'name' => 'Enter delivery state: Cancelled',
                'nameDe' => 'Eintritt Lieferstatus: Abgebrochen',
            ],
        ];
    }

    private function updateMailTemplateTypeTranslation(Connection $connection, string $technicalName, string $name, ?string $languageId): void
    {
        if ($languageId === null) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            UPDATE mail_template_type_translation
            SET name = :name
            WHERE mail_template_type_id = (
                SELECT id FROM mail_template_type WHERE technical_name = :technicalName
            ) AND language_id = :languageId
            SQL, [
            'name' => $name,
            'technicalName' => $technicalName,
            'languageId' => $languageId,
        ], [
            'name' => ParameterType::STRING,
            'technicalName' => ParameterType::STRING,
            'languageId' => ParameterType::BINARY,
        ]);
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $languageId = $connection->fetchOne(<<<'SQL'
            SELECT language.id
            FROM language
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE locale.code = :code
            SQL, ['code' => $locale]);

        if (\is_resource($languageId)) {
            $languageId = stream_get_contents($languageId);
        }

        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        return $languageId ? (string) $languageId : Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
    }
}
