<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
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
class Migration1570622696CustomerPasswordRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570622696;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS customer_recovery (
                id BYTEA NOT NULL,
                customer_id BYTEA NOT NULL,
                hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(3) NOT NULL,
                updated_at TIMESTAMP(3) NULL,
                PRIMARY KEY (id),
                CONSTRAINT "uniq.customer_recovery.customer_id" UNIQUE (customer_id),
                CONSTRAINT "fk.customer_recovery.customer_id" FOREIGN KEY (customer_id)
                    REFERENCES customer (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
            SQL);

        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE mail_template_type IN SHARE ROW EXCLUSIVE MODE');

            $mailTemplateTypeId = $connection->fetchOne(
                'SELECT id FROM mail_template_type WHERE technical_name = ?',
                ['customer.recovery.request'],
            );
            if ($mailTemplateTypeId !== false) {
                return;
            }

            $mailTemplateTypeId = $this->createMailTemplateType($connection);
            $this->createMailTemplate($connection, $mailTemplateTypeId);
            $this->registerEventAction($connection, $mailTemplateTypeId);
        });
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT language.id
FROM language
INNER JOIN locale ON locale.id = language.locale_id
WHERE locale.code = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
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

    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technical_name' => 'customer.recovery.request',
            'available_entities' => json_encode(['customerRecovery' => 'customer_recovery']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $defaultLangId,
                'name' => 'Customer password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Customer password recovery',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $deLangId,
                'name' => 'Benutzer Passwort Wiederherstellung',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
        }

        return $mailTemplateTypeId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $defaultLangId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password recovery',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $deLangId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password Wiederherstellung',
                'description' => '',
                'content_html' => $this->getContentHtmlDe(),
                'content_plain' => $this->getContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
        }
    }

    private function registerEventAction(Connection $connection, string $mailTemplateTypeId): void
    {
        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => 'customer.recovery.request',
            'action_name' => SendMailAction::ACTION_NAME,
            'config' => json_encode(['mail_template_type_id' => $mailTemplateTypeId], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.<br/>
        <br/>
        Yours sincerely
        Your {{ shopName }} team
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Hello {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        You have requested a new password for your {{ shopName }} account.
        Click on the following link to reset your password:

        {{ resetUrl }}

        This link is valid for the next 2 hours.
        If you don't want to reset your password, ignore this email and no changes will be made.

        Yours sincerely
        Your {{ shopName }}-Team
MAIL;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},<br/>
        <br/>
        Sie haben ein neues Passwort für Ihren {{ shopName }}-Account angefordert.
        Klicken Sie auf folgenden Link, um Ihr Passwort zurückzusetzen:<br/>
        <br/>
        <a href="{{ resetUrl }}">{{ resetUrl }}</a><br/>
        <br/>
        Dieser Link ist für die nächsten 2 Stunden gültig.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.<br/>
        <br/>
        Mit freundlichen Grüßen
        Ihr {{ shopName }}-Team
    </p>
</div>
MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
        Hallo {{ customerRecovery.customer.firstName }} {{ customerRecovery.customer.lastName }},

        Sie haben ein neues Passwort für Ihren {{ shopName }}-Account angefordert.
        Klicken Sie auf folgenden Link, um Ihr Passwort zurückzusetzen:

        {{ resetUrl }}

        Dieser Link ist für die nächsten 2 Stunden gültig.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.

        Mit freundlichen Grüßen
        Ihr {{ shopName }}-Team
MAIL;
    }
}
