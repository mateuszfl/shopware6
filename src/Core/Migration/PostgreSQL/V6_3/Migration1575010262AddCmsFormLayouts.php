<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1575010262AddCmsFormLayouts extends MigrationStep
{
    private const CONTACT = 'contact';
    private const NEWSLETTER = 'newsletter';
    private const CONTACT_DE = 'Kontakt';
    private const NEWSLETTER_DE = 'Newsletter';

    public function getCreationTimestamp(): int
    {
        return 1575010262;
    }

    public function update(Connection $connection): void
    {
        $this->addDefaultContactFormLayout($connection, self::CONTACT, self::CONTACT_DE);
        $this->addDefaultContactFormLayout($connection, self::NEWSLETTER, self::NEWSLETTER_DE);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addDefaultContactFormLayout(Connection $connection, string $formType, string $formTypeDe): void
    {
        $slotTranslations = [];
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = $this->getLanguageDeId($connection);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        // cms page
        $page = ['id' => Uuid::randomBytes(), 'type' => 'page', 'locked' => 1, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $pageEng = ['cms_page_id' => $page['id'], 'language_id' => $languageEn, 'name' => 'Default shop page layout with ' . $formType . ' form', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $pageDeu = ['cms_page_id' => $page['id'], 'language_id' => $languageDe, 'name' => 'Standard Shopseiten-Layout mit ' . $formTypeDe . 'formular', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $connection->insert('cms_page', $page, ['id' => ParameterType::BINARY]);
        $connection->insert('cms_page_translation', $pageEng, [
            'cms_page_id' => ParameterType::BINARY,
            'language_id' => ParameterType::BINARY,
        ]);
        if ($languageDe) {
            $connection->insert('cms_page_translation', $pageDeu, [
                'cms_page_id' => ParameterType::BINARY,
                'language_id' => ParameterType::BINARY,
            ]);
        }
        $section = ['id' => Uuid::randomBytes(), 'cms_page_id' => $page['id'], 'position' => 0, 'type' => 'default', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
        $connection->insert('cms_section', $section, [
            'id' => ParameterType::BINARY,
            'cms_page_id' => ParameterType::BINARY,
        ]);
        // cms block
        $name = ucfirst($formType) . ' form';
        $block = ['id' => Uuid::randomBytes(), 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'cms_section_id' => $section['id'], 'locked' => 1, 'position' => 1, 'type' => 'form', 'name' => $name, 'margin_top' => '20px', 'margin_bottom' => '20px', 'margin_left' => '20px', 'margin_right' => '20px', 'background_media_mode' => 'cover'];
        $connection->insert('cms_block', $block, [
            'id' => ParameterType::BINARY,
            'cms_section_id' => ParameterType::BINARY,
        ]);
        // cms slot
        $slot = ['id' => Uuid::randomBytes(), 'locked' => 1, 'cms_block_id' => $block['id'], 'type' => 'form', 'slot' => 'content', 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'version_id' => $versionId];
        $slotTranslationData = ['cms_slot_id' => $slot['id'], 'cms_slot_version_id' => $versionId, 'language_id' => $languageEn, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'config' => json_encode(['type' => ['source' => 'static', 'value' => $formType], 'mailReceiver' => ['source' => 'static', 'value' => []], 'confirmationText' => ['source' => 'static', 'value' => '']])];
        $slotTranslationData['language_id'] = $languageEn;
        $slotTranslations[] = $slotTranslationData;
        if ($languageDe !== null) {
            $slotTranslationData['language_id'] = $languageDe;
            $slotTranslations[] = $slotTranslationData;
        }
        $connection->insert('cms_slot', $slot, [
            'id' => ParameterType::BINARY,
            'cms_block_id' => ParameterType::BINARY,
            'version_id' => ParameterType::BINARY,
        ]);
        foreach ($slotTranslations as $translation) {
            $connection->insert('cms_slot_translation', $translation, [
                'cms_slot_id' => ParameterType::BINARY,
                'cms_slot_version_id' => ParameterType::BINARY,
                'language_id' => ParameterType::BINARY,
            ]);
        }
    }

    private function getLanguageDeId(Connection $connection): ?string
    {
        $result = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = \'de-DE\'');
        $languageId = $this->normalizeBinaryValue($result);
        if ($languageId === null || Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM) === $languageId) {
            return null;
        }

        return $languageId;
    }

    private function normalizeBinaryValue(mixed $value): ?string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
