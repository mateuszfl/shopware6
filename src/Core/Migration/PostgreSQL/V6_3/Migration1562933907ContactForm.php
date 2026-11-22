<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
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
class Migration1562933907ContactForm extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562933907;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE mail_template_type IN SHARE ROW EXCLUSIVE MODE');

            if ($connection->fetchOne('SELECT 1 FROM mail_template_type WHERE technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]) !== false) {
                return;
            }

            $mailTemplateTypeId = Uuid::randomHex();
            $mailTemplateTypeBytes = Uuid::fromHexToBytes($mailTemplateTypeId);
            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeBytes,
                'technical_name' => MailTemplateTypes::MAILTYPE_CONTACT_FORM,
                'available_entities' => json_encode(['salesChannel' => 'sales_channel'], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

            $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
            $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');
            $systemLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

            if ($defaultLangId !== null && $defaultLangId !== $deLangId) {
                $this->insertTranslation($connection, $mailTemplateTypeBytes, $defaultLangId, 'Contact form');
            }

            if ($defaultLangId !== $systemLangId) {
                $this->insertTranslation($connection, $mailTemplateTypeBytes, $systemLangId, 'Contact form');
            }

            if ($deLangId !== null) {
                $this->insertTranslation($connection, $mailTemplateTypeBytes, $deLangId, 'Kontaktformular');
            }

            $connection->insert('event_action', [
                'id' => Uuid::randomBytes(),
                'event_name' => ContactFormEvent::EVENT_NAME,
                'action_name' => SendMailAction::ACTION_NAME,
                'config' => json_encode(['mail_template_type_id' => $mailTemplateTypeId], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        });
    }

    private function insertTranslation(Connection $connection, string $mailTemplateTypeId, string $languageId, string $name): void
    {
        $connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $mailTemplateTypeId,
            'name' => $name,
            'language_id' => $languageId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING]);
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

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }
}
