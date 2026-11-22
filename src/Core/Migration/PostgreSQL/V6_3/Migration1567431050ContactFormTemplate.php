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
class Migration1567431050ContactFormTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1567431050;
    }

    public function update(Connection $connection): void
    {
        $contactEventConfig = $connection->fetchOne(
            'SELECT config FROM event_action WHERE event_name = ? AND action_name = ?',
            [ContactFormEvent::EVENT_NAME, SendMailAction::ACTION_NAME]
        );
        $config = json_decode((string) $contactEventConfig, true, flags: \JSON_THROW_ON_ERROR);
        $contactTemplateTypeId = Uuid::fromHexToBytes($config['mail_template_type_id']);

        $contactTemplateId = $connection->fetchOne(
            'SELECT mail_template.id FROM mail_template LEFT JOIN mail_template_type ON mail_template.mail_template_type_id = mail_template_type.id WHERE mail_template_type.technical_name = ?',
            [MailTemplateTypes::MAILTYPE_CONTACT_FORM]
        );
        if (\is_resource($contactTemplateId)) {
            $contactTemplateId = stream_get_contents($contactTemplateId);
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        if (\is_string($contactTemplateId)) {
            $connection->update('mail_template', ['updated_at' => $createdAt], ['id' => $contactTemplateId], [ParameterType::STRING, ParameterType::BINARY]);
            $connection->delete('mail_template_translation', ['mail_template_id' => $contactTemplateId], [ParameterType::BINARY]);
        } else {
            $contactTemplateId = Uuid::randomBytes();
            $connection->insert('mail_template', [
                'id' => $contactTemplateId,
                'mail_template_type_id' => $contactTemplateTypeId,
                'system_default' => 1,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
        }

        $connection->insert('mail_template_translation', [
            'subject' => 'Contact form received - {{ salesChannel.name }}',
            'description' => 'Contact form received',
            'sender_name' => '{{ salesChannel.name }}',
            'content_html' => $this->getRegistrationHtmlTemplateEn(),
            'content_plain' => $this->getRegistrationPlainTemplateEn(),
            'created_at' => $createdAt,
            'mail_template_id' => $contactTemplateId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Following Message was sent to you by {{ contactFormData.firstName }} {{ contactFormData.lastName }} via the contact form.<br/>
                <br/>
                Contact email address: {{ contactFormData.email }}<br/>
                Phone: {{ contactFormData.phone }}<br/><br/>
                Subject: {{ contactFormData.subject }}<br/>
                <br/>
                Message: {{ contactFormData.comment }}<br/>
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return 'Following Message was sent to you by {{ contactFormData.firstName }} {{ contactFormData.lastName }} via the contact form.

                Contact email address: {{ contactFormData.email }}
                Phone: {{ contactFormData.phone }}

                Subject: {{ contactFormData.subject }}

                Message: {{ contactFormData.comment }}';
    }
}
