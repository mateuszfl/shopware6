<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1573569685DoubleOptInGuestMailTemplate extends MigrationStep
{
    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1573569685;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('LOCK TABLE mail_template_type IN SHARE ROW EXCLUSIVE MODE');

            $templateTypeId = $connection->fetchOne(
                'SELECT id FROM mail_template_type WHERE technical_name = ?',
                ['guest_order.double_opt_in'],
            );
            if (\is_resource($templateTypeId)) {
                $templateTypeId = stream_get_contents($templateTypeId);
            }
            if (!\is_string($templateTypeId)) {
                $templateTypeId = Uuid::randomBytes();
                $this->insertMailTemplateTypeData($templateTypeId, $connection);
            }

            $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = ?', [$templateTypeId], [ParameterType::BINARY]);
            if (\is_resource($templateId)) {
                $templateId = stream_get_contents($templateId);
            }
            if (!\is_string($templateId)) {
                $templateId = Uuid::randomBytes();
                $connection->insert('mail_template', [
                    'id' => $templateId,
                    'mail_template_type_id' => $templateTypeId,
                    'system_default' => 1,
                    'created_at' => $this->now(),
                ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
            }

            $this->insertMailTemplateTranslations($templateId, $connection);
            $this->insertEventActionData($templateTypeId, $connection);
        });
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        $languageId = $connection->fetchOne('SELECT id FROM language WHERE name = :name', ['name' => $languageName]);
        if (\is_resource($languageId)) {
            $languageId = stream_get_contents($languageId);
        }

        return \is_string($languageId) ? $languageId : null;
    }

    private function insertMailTemplateTypeData(string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => 'guest_order.double_opt_in',
                'available_entities' => $this->getAvailableEntities(),
                'created_at' => $this->now(),
            ],
            [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);
        $translations = [bin2hex($defaultLanguageId) => 'Double opt in guest order'];
        if ($englishLanguageId !== null) {
            $translations[bin2hex($englishLanguageId)] = 'Double opt in guest order';
        }
        if ($germanLanguageId !== null) {
            $translations[bin2hex($germanLanguageId)] = 'Double-Opt-In-Gast-Bestellung';
        }
        foreach ($translations as $languageHex => $name) {
            $languageId = Uuid::fromHexToBytes($languageHex);
            $this->insertTypeTranslation($connection, $templateTypeId, $languageId, $name);
        }
    }

    private function insertTypeTranslation(Connection $connection, string $templateTypeId, string $languageId, string $name): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM mail_template_type_translation WHERE mail_template_type_id = :typeId AND language_id = :languageId',
            ['typeId' => $templateTypeId, 'languageId' => $languageId],
            ['typeId' => ParameterType::BINARY, 'languageId' => ParameterType::BINARY],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => $templateTypeId,
            'language_id' => $languageId,
            'name' => $name,
            'created_at' => $this->now(),
        ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
    }

    private function insertMailTemplateTranslations(string $templateId, Connection $connection): void
    {
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);
        $translations = [];
        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $translations[bin2hex($defaultLanguageId)] = false;
        }
        if ($englishLanguageId !== null) {
            $translations[bin2hex($englishLanguageId)] = false;
        }
        if ($germanLanguageId !== null) {
            $translations[bin2hex($germanLanguageId)] = true;
        }

        foreach ($translations as $languageHex => $german) {
            $languageId = Uuid::fromHexToBytes($languageHex);
            $exists = $connection->fetchOne(
                'SELECT 1 FROM mail_template_translation WHERE mail_template_id = :templateId AND language_id = :languageId',
                ['templateId' => $templateId, 'languageId' => $languageId],
                ['templateId' => ParameterType::BINARY, 'languageId' => ParameterType::BINARY],
            );
            if ($exists !== false) {
                continue;
            }

            $connection->insert('mail_template_translation', [
                'subject' => $german ? 'Bitte bestätigen Sie Ihre E-Mail-Adresse bei {{ salesChannel.name }}' : 'Please confirm your email address at {{ salesChannel.name }}',
                'description' => $german ? 'Anmeldebestätigung bei Gastbestellungen' : 'Email confirmation at guest orders',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $german ? $this->getHtmlTemplateDe() : $this->getHtmlTemplateEn(),
                'content_plain' => $german ? $this->getPlainTemplateDe() : $this->getPlainTemplateEn(),
                'created_at' => $this->now(),
                'mail_template_id' => $templateId,
                'language_id' => $languageId,
            ], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);
        }
    }

    private function insertEventActionData(string $templateTypeId, Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM event_action WHERE event_name = :eventName AND action_name = :actionName',
            ['eventName' => DoubleOptInGuestOrderEvent::EVENT_NAME, 'actionName' => SendMailAction::ACTION_NAME],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'event_name' => DoubleOptInGuestOrderEvent::EVENT_NAME,
            'action_name' => SendMailAction::ACTION_NAME,
            'config' => json_encode(['mail_template_type_id' => Uuid::fromBytesToHex($templateTypeId)], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
    }

    private function now(): string
    {
        return (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function getHtmlTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                    <br/>
                    Bitte bestätigen Sie Ihre E-Mail-Adresse über den nachfolgenden Link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">E-Mail bestätigen</a><br/>
                    <br/>
                    Nach der Bestätigung werden Sie in den Bestellabschluss geleitet, dort können Sie Ihre Bestellung nochmals überprüfen und abschließen.<br/>
                    Durch diese Bestätigung erklären Sie sich ebenso damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateDe(): string
    {
        return '
            Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},

            Bitte bestätigen Sie Ihre E-Mail-Adresse über den nachfolgenden Link:

            {{ confirmUrl }}

            Nach der Bestätigung werden Sie in den Bestellabschluss geleitet, dort können Sie Ihre Bestellung nochmals überprüfen und abschließen.
            Durch diese Bestätigung erklären Sie sich ebenso damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.
        ';
    }

    private function getHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hello {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                    <br/>
                    Please confirm your email address via the following link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">Confirm email</a><br/>
                    <br/>
                    After the confirmation, you will be directed to the checkout, where you can check and complete your order again.<br/>
                    By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateEn(): string
    {
        return '
            Hello {{ customer.salutation.displayName }} {{ customer.lastName }},

            Please confirm your email address via the following link:

            {{ confirmUrl }}

            After the confirmation, you will be directed to the checkout, where you can check and complete your order again.
            By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
        ';
    }

    private function getAvailableEntities(): string
    {
        return '{"customer":"customer","salesChannel":"sales_channel"}';
    }
}
