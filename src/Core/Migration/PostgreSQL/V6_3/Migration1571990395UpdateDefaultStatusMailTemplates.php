<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
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
class Migration1571990395UpdateDefaultStatusMailTemplates extends MigrationStep
{
    private ?string $defaultLangId = null;

    private ?string $deLangId = null;

    public function getCreationTimestamp(): int
    {
        return 1571990395;
    }

    public function update(Connection $connection): void
    {
        // update DELIVERY_STATE_SHIPPED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially delivered',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise ausgeliefert'
        );

        // update DELIVERY_STATE_RETURNED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially returned',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise retourniert'
        );

        // update DELIVERY_STATE_RETURNED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is returned',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde retourniert'
        );

        // update DELIVERY_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Stornierung der Bestellung bei {{ salesChannel.name }}'
        );

        // update DELIVERY_STATE_SHIPPED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is delivered',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde ausgeliefert'
        );

        // update ORDER_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is open',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist offen'
        );

        // update ORDER_STATE_IN_PROGRESS
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is in process',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist in Bearbeitung'
        );

        // update ORDER_STATE_COMPLETED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completed',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist komplett abgeschlossen'
        );

        // update ORDER_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Stornierung der Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_REFUNDED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise erstattet'
        );

        // update TRANSACTION_STATE_REMINDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Reminder for your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Zahlungserinnerung für die Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_PAID
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completly paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }} wurde komplett bezahlt'
        );

        // update TRANSACTION_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'The payment for your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Die Zahlung für ihre Bestellung bei {{ salesChannel.name }} wurde storniert'
        );

        // update TRANSACTION_STATE_REFUNDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde erstattet'
        );

        // update TRANSACTION_STATE_PAID_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }} wurde teilweise bezahlt'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function changeMailTemplateNameForType(Connection $connection, string $mailTemplateType): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE mail_template_type
            SET technical_name = REPLACE(technical_name, 'state_enter.', '')
            WHERE technical_name = :type
            SQL, ['type' => $mailTemplateType]);
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $languageId = $connection->fetchOne(<<<'SQL'
            SELECT language.id
            FROM language
            INNER JOIN locale ON language.locale_id = locale.id
            WHERE locale.code = :code
            LIMIT 1
            SQL, ['code' => $code]);

        if (\is_resource($languageId)) {
            $languageId = stream_get_contents($languageId);
        }
        if (!$languageId && $code !== 'en-GB') {
            return null;
        }

        return $languageId ? (string) $languageId : Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateType,
        string $contentHtmlEn,
        string $contentPlainEn,
        string $senderNameEn,
        string $subjectEn,
        string $contentHtmlDe,
        string $contentPlainDe,
        string $senderNameDe,
        string $subjectDe
    ): void {
        $this->defaultLangId ??= $this->fetchLanguageId('en-GB', $connection);
        $this->deLangId ??= $this->fetchLanguageId('de-DE', $connection);

        $prefixedType = 'state_enter.' . $mailTemplateType;
        $templateTypeId = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name IN (:prefixedType, :type) ORDER BY CASE WHEN technical_name = :prefixedType THEN 0 ELSE 1 END LIMIT 1',
            ['prefixedType' => $prefixedType, 'type' => $mailTemplateType]
        );
        if (\is_resource($templateTypeId)) {
            $templateTypeId = stream_get_contents($templateTypeId);
        }
        if (!\is_string($templateTypeId)) {
            return;
        }

        $this->changeMailTemplateNameForType($connection, $prefixedType);

        $templateId = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId AND updated_at IS NULL ORDER BY system_default DESC LIMIT 1',
            ['typeId' => $templateTypeId],
            ['typeId' => ParameterType::BINARY]
        );
        if (\is_resource($templateId)) {
            $templateId = stream_get_contents($templateId);
        }

        $descriptionEn = 'Shopware Default Template';
        $descriptionDe = 'Shopware Basis Template';
        if (!\is_string($templateId)) {
            $templateId = Uuid::randomBytes();
            $connection->insert('mail_template', [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => true,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
        } else {
            $connection->executeStatement(
                'UPDATE mail_template SET system_default = 1 WHERE id = :templateId',
                ['templateId' => $templateId],
                ['templateId' => ParameterType::BINARY]
            );
        }

        if ($this->defaultLangId !== $this->deLangId && $this->defaultLangId !== null) {
            $this->upsertMailTemplateTranslation(
                $connection,
                $templateId,
                $this->defaultLangId,
                $subjectEn,
                $descriptionEn,
                $senderNameEn,
                $contentHtmlEn,
                $contentPlainEn
            );
        }

        if ($this->deLangId !== null) {
            $this->upsertMailTemplateTranslation(
                $connection,
                $templateId,
                $this->deLangId,
                $subjectDe,
                $descriptionDe,
                $senderNameDe,
                $contentHtmlDe,
                $contentPlainDe
            );
        }
    }

    private function upsertMailTemplateTranslation(
        Connection $connection,
        string $templateId,
        string $languageId,
        string $subject,
        string $description,
        string $senderName,
        string $contentHtml,
        string $contentPlain
    ): void {
        $params = [
            'templateId' => $templateId,
            'languageId' => $languageId,
            'subject' => $subject,
            'description' => $description,
            'senderName' => $senderName,
            'contentHtml' => $contentHtml,
            'contentPlain' => $contentPlain,
        ];
        $types = [
            'templateId' => ParameterType::BINARY,
            'languageId' => ParameterType::BINARY,
            'subject' => ParameterType::STRING,
            'description' => ParameterType::STRING,
            'senderName' => ParameterType::STRING,
            'contentHtml' => ParameterType::STRING,
            'contentPlain' => ParameterType::STRING,
        ];
        $exists = $connection->fetchOne(
            'SELECT 1 FROM mail_template_translation WHERE mail_template_id = :templateId AND language_id = :languageId',
            ['templateId' => $templateId, 'languageId' => $languageId],
            ['templateId' => ParameterType::BINARY, 'languageId' => ParameterType::BINARY]
        );
        if ($exists !== false) {
            $connection->executeStatement(<<<'SQL'
                UPDATE mail_template_translation
                SET subject = :subject,
                    description = :description,
                    sender_name = :senderName,
                    content_html = :contentHtml,
                    content_plain = :contentPlain
                WHERE mail_template_id = :templateId AND language_id = :languageId
                SQL, $params, $types);

            return;
        }

        $connection->insert('mail_template_translation', [
            'mail_template_id' => $templateId,
            'language_id' => $languageId,
            'subject' => $subject,
            'description' => $description,
            'sender_name' => $senderName,
            'content_html' => $contentHtml,
            'content_plain' => $contentPlain,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            ParameterType::BINARY,
            ParameterType::BINARY,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);
    }

    private function getOrderStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderStatusUpdateHtmlTemplateDe(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                <br/>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
            </p>
        </div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateDe(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateDe(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                <br/>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
            </p>
        </div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateDe(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
EOT;
    }
}
