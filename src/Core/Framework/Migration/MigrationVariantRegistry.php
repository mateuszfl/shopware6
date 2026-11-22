<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\PostgreSQL\V6_3 as PostgreSQL63;
use Shopware\Core\Migration\V6_3 as Core63;

/**
 * Explicit PostgreSQL implementations. An identity always belongs to the original source.
 * Core entries live here so the installer can resolve them before the container boots.
 *
 * @internal
 */
#[Package('framework')]
final class MigrationVariantRegistry
{
    /**
     * @var list<array{class-string<MigrationStep>, int, class-string<MigrationStep>}>
     */
    private const CORE_VARIANTS = [
        [Core63\Migration1536232600Language::class, 1536232600, PostgreSQL63\Migration1536232600Language::class],
        [Core63\Migration1536232610Locale::class, 1536232610, PostgreSQL63\Migration1536232610Locale::class],
        [Core63\Migration1536232620SalesChannelType::class, 1536232620, PostgreSQL63\Migration1536232620SalesChannelType::class],
        [Core63\Migration1536232630PropertyGroup::class, 1536232630, PostgreSQL63\Migration1536232630PropertyGroup::class],
        [Core63\Migration1536232640Currency::class, 1536232640, PostgreSQL63\Migration1536232640Currency::class],
        [Core63\Migration1536232650CustomerGroup::class, 1536232650, PostgreSQL63\Migration1536232650CustomerGroup::class],
        [Core63\Migration1536232660Tax::class, 1536232660, PostgreSQL63\Migration1536232660Tax::class],
        [Core63\Migration1536232670Unit::class, 1536232670, PostgreSQL63\Migration1536232670Unit::class],
        [Core63\Migration1536232680Rule::class, 1536232680, PostgreSQL63\Migration1536232680Rule::class],
        [Core63\Migration1536232690Version::class, 1536232690, PostgreSQL63\Migration1536232690Version::class],
        [Core63\Migration1536232700VersionCommit::class, 1536232700, PostgreSQL63\Migration1536232700VersionCommit::class],
        [Core63\Migration1536232710Integration::class, 1536232710, PostgreSQL63\Migration1536232710Integration::class],
        [Core63\Migration1536232720Country::class, 1536232720, PostgreSQL63\Migration1536232720Country::class],
        [Core63\Migration1536232730CountryState::class, 1536232730, PostgreSQL63\Migration1536232730CountryState::class],
        [Core63\Migration1536232740SnippetSet::class, 1536232740, PostgreSQL63\Migration1536232740SnippetSet::class],
        [Core63\Migration1536232750Snippet::class, 1536232750, PostgreSQL63\Migration1536232750Snippet::class],
        [Core63\Migration1536232760StateMachine::class, 1536232760, PostgreSQL63\Migration1536232760StateMachine::class],
        [Core63\Migration1536232770VersionCommitData::class, 1536232770, PostgreSQL63\Migration1536232770VersionCommitData::class],
        [Core63\Migration1536232790MailHeaderFooter::class, 1536232790, PostgreSQL63\Migration1536232790MailHeaderFooter::class],
        [Core63\Migration1536232800DeliveryTime::class, 1536232800, PostgreSQL63\Migration1536232800DeliveryTime::class],
        [Core63\Migration1536232810User::class, 1536232810, PostgreSQL63\Migration1536232810User::class],
        [Core63\Migration1536232820UserAccessKey::class, 1536232820, PostgreSQL63\Migration1536232820UserAccessKey::class],
        [Core63\Migration1536232830MediaDefaultFolder::class, 1536232830, PostgreSQL63\Migration1536232830MediaDefaultFolder::class],
        [Core63\Migration1536232840MediaFolder::class, 1536232840, PostgreSQL63\Migration1536232840MediaFolder::class],
        [Core63\Migration1536232850Media::class, 1536232850, PostgreSQL63\Migration1536232850Media::class],
        [Core63\Migration1536232860ShippingMethod::class, 1536232860, PostgreSQL63\Migration1536232860ShippingMethod::class],
        [Core63\Migration1536232870ShippingMethodPrice::class, 1536232870, PostgreSQL63\Migration1536232870ShippingMethodPrice::class],
        [Core63\Migration1536232880Category::class, 1536232880, PostgreSQL63\Migration1536232880Category::class],
        [Core63\Migration1536232890CmsPage::class, 1536232890, PostgreSQL63\Migration1536232890CmsPage::class],
        [Core63\Migration1536232900CmsBlock::class, 1536232900, PostgreSQL63\Migration1536232900CmsBlock::class],
        [Core63\Migration1536232910CmsSlot::class, 1536232910, PostgreSQL63\Migration1536232910CmsSlot::class],
        [Core63\Migration1536232920PaymentMethod::class, 1536232920, PostgreSQL63\Migration1536232920PaymentMethod::class],
        [Core63\Migration1536232930Navigation::class, 1536232930, PostgreSQL63\Migration1536232930Navigation::class],
        [Core63\Migration1536232940SalesChannel::class, 1536232940, PostgreSQL63\Migration1536232940SalesChannel::class],
        [Core63\Migration1536232950Salutation::class, 1536232950, PostgreSQL63\Migration1536232950Salutation::class],
        [Core63\Migration1536232960Customer::class, 1536232960, PostgreSQL63\Migration1536232960Customer::class],
        [Core63\Migration1536232970CustomerAddress::class, 1536232970, PostgreSQL63\Migration1536232970CustomerAddress::class],
        [Core63\Migration1536232980Cart::class, 1536232980, PostgreSQL63\Migration1536232980Cart::class],
        [Core63\Migration1536232990Order::class, 1536232990, PostgreSQL63\Migration1536232990Order::class],
        [Core63\Migration1536233000OrderCustomer::class, 1536233000, PostgreSQL63\Migration1536233000OrderCustomer::class],
        [Core63\Migration1536233010OrderAddress::class, 1536233010, PostgreSQL63\Migration1536233010OrderAddress::class],
        [Core63\Migration1536233020OrderDelivery::class, 1536233020, PostgreSQL63\Migration1536233020OrderDelivery::class],
        [Core63\Migration1536233030OrderLineItem::class, 1536233030, PostgreSQL63\Migration1536233030OrderLineItem::class],
        [Core63\Migration1536233040OrderDeliveryPosition::class, 1536233040, PostgreSQL63\Migration1536233040OrderDeliveryPosition::class],
        [Core63\Migration1536233050OrderTransaction::class, 1536233050, PostgreSQL63\Migration1536233050OrderTransaction::class],
        [Core63\Migration1536233060MediaFolderConfiguration::class, 1536233060, PostgreSQL63\Migration1536233060MediaFolderConfiguration::class],
        [Core63\Migration1536233070MediaThumbnailSize::class, 1536233070, PostgreSQL63\Migration1536233070MediaThumbnailSize::class],
        [Core63\Migration1536233080MediaFolderConfigurationMediaThumbnailSize::class, 1536233080, PostgreSQL63\Migration1536233080MediaFolderConfigurationMediaThumbnailSize::class],
        [Core63\Migration1536233090MediaThumbnail::class, 1536233090, PostgreSQL63\Migration1536233090MediaThumbnail::class],
        [Core63\Migration1536233100PropertyGroupOption::class, 1536233100, PostgreSQL63\Migration1536233100PropertyGroupOption::class],
        [Core63\Migration1536233110ProductManufacturer::class, 1536233110, PostgreSQL63\Migration1536233110ProductManufacturer::class],
        [Core63\Migration1536233120Product::class, 1536233120, PostgreSQL63\Migration1536233120Product::class],
        [Core63\Migration1536233130ProductMedia::class, 1536233130, PostgreSQL63\Migration1536233130ProductMedia::class],
        [Core63\Migration1536233140ProductProperty::class, 1536233140, PostgreSQL63\Migration1536233140ProductProperty::class],
        [Core63\Migration1536233150ProductOption::class, 1536233150, PostgreSQL63\Migration1536233150ProductOption::class],
        [Core63\Migration1536233160ProductConfigurator::class, 1536233160, PostgreSQL63\Migration1536233160ProductConfigurator::class],
        [Core63\Migration1536233170ProductCategoryTree::class, 1536233170, PostgreSQL63\Migration1536233170ProductCategoryTree::class],
        [Core63\Migration1536233180ProductCategory::class, 1536233180, PostgreSQL63\Migration1536233180ProductCategory::class],
        [Core63\Migration1536233190ProductPriceRule::class, 1536233190, PostgreSQL63\Migration1536233190ProductPriceRule::class],
        [Core63\Migration1536233200RuleCondition::class, 1536233200, PostgreSQL63\Migration1536233200RuleCondition::class],
        [Core63\Migration1536233210SalesChannelDomain::class, 1536233210, PostgreSQL63\Migration1536233210SalesChannelDomain::class],
        [Core63\Migration1536233220PluginTranslation::class, 1536233220, PostgreSQL63\Migration1536233220PluginTranslation::class],
        [Core63\Migration1536233230ProductStream::class, 1536233230, PostgreSQL63\Migration1536233230ProductStream::class],
        [Core63\Migration1536233240ProductStreamFilter::class, 1536233240, PostgreSQL63\Migration1536233240ProductStreamFilter::class],
        [Core63\Migration1536233250MessageQueueStats::class, 1536233250, PostgreSQL63\Migration1536233250MessageQueueStats::class],
        [Core63\Migration1536233260StateMachineHistory::class, 1536233260, PostgreSQL63\Migration1536233260StateMachineHistory::class],
        [Core63\Migration1536233270SystemConfig::class, 1536233270, PostgreSQL63\Migration1536233270SystemConfig::class],
        [Core63\Migration1536233280CustomFieldSet::class, 1536233280, PostgreSQL63\Migration1536233280CustomFieldSet::class],
        [Core63\Migration1536233290CustomFieldSetRelation::class, 1536233290, PostgreSQL63\Migration1536233290CustomFieldSetRelation::class],
        [Core63\Migration1536233300CustomField::class, 1536233300, PostgreSQL63\Migration1536233300CustomField::class],
        [Core63\Migration1536233310ScheduledTask::class, 1536233310, PostgreSQL63\Migration1536233310ScheduledTask::class],
        [Core63\Migration1536233320DeadMessage::class, 1536233320, PostgreSQL63\Migration1536233320DeadMessage::class],
        [Core63\Migration1536233330MailTemplate::class, 1536233330, PostgreSQL63\Migration1536233330MailTemplate::class],
        [Core63\Migration1536233340NumberRange::class, 1536233340, PostgreSQL63\Migration1536233340NumberRange::class],
        [Core63\Migration1536233350ProductVisibility::class, 1536233350, PostgreSQL63\Migration1536233350ProductVisibility::class],
        [Core63\Migration1536233360Document::class, 1536233360, PostgreSQL63\Migration1536233360Document::class],
        [Core63\Migration1536233370EventAction::class, 1536233370, PostgreSQL63\Migration1536233370EventAction::class],
        [Core63\Migration1536233380UserRecovery::class, 1536233380, PostgreSQL63\Migration1536233380UserRecovery::class],
        [Core63\Migration1536233390Promotion::class, 1536233390, PostgreSQL63\Migration1536233390Promotion::class],
        [Core63\Migration1536233400MailTemplateMedia::class, 1536233400, PostgreSQL63\Migration1536233400MailTemplateMedia::class],
        [Core63\Migration1536233410PromotionSalesChannel::class, 1536233410, PostgreSQL63\Migration1536233410PromotionSalesChannel::class],
        [Core63\Migration1536233420PromotionDiscount::class, 1536233420, PostgreSQL63\Migration1536233420PromotionDiscount::class],
        [Core63\Migration1536233430NewsletterRecipient::class, 1536233430, PostgreSQL63\Migration1536233430NewsletterRecipient::class],
        [Core63\Migration1536233440PromotionPersonaCustomer::class, 1536233440, PostgreSQL63\Migration1536233440PromotionPersonaCustomer::class],
        [Core63\Migration1536233450PromotionPersonaRules::class, 1536233450, PostgreSQL63\Migration1536233450PromotionPersonaRules::class],
        [Core63\Migration1536233460NumberRangeTranslationAndConfiguration::class, 1536233460, Core63\Migration1536233460NumberRangeTranslationAndConfiguration::class],
        [Core63\Migration1536233470PromotionOrderRule::class, 1536233470, PostgreSQL63\Migration1536233470PromotionOrderRule::class],
        [Core63\Migration1536233480SalesChannelApiContext::class, 1536233480, PostgreSQL63\Migration1536233480SalesChannelApiContext::class],
        [Core63\Migration1536233500PromotionDiscountRule::class, 1536233500, PostgreSQL63\Migration1536233500PromotionDiscountRule::class],
        [Core63\Migration1536233510DocumentConfiguration::class, 1536233510, PostgreSQL63\Migration1536233510DocumentConfiguration::class],
        [Core63\Migration1536233520PromotionCartRule::class, 1536233520, PostgreSQL63\Migration1536233520PromotionCartRule::class],
        [Core63\Migration1536233530SalesChannelCategoryId::class, 1536233530, PostgreSQL63\Migration1536233530SalesChannelCategoryId::class],
        [Core63\Migration1536233540ProductSearchKeyword::class, 1536233540, PostgreSQL63\Migration1536233540ProductSearchKeyword::class],
        [Core63\Migration1536233550Tag::class, 1536233550, PostgreSQL63\Migration1536233550Tag::class],
        [Core63\Migration1536233560BasicData::class, 1536233560, PostgreSQL63\Migration1536233560BasicData::class],
        [Core63\Migration1552360944MediaFolderConfigurationNoAssoc::class, 1552360944, PostgreSQL63\Migration1552360944MediaFolderConfigurationNoAssoc::class],
        [Core63\Migration1554199340AddImportExportProfile::class, 1554199340, PostgreSQL63\Migration1554199340AddImportExportProfile::class],
        [Core63\Migration1554200141ImportExportFile::class, 1554200141, PostgreSQL63\Migration1554200141ImportExportFile::class],
        [Core63\Migration1554203706AddImportExportLog::class, 1554203706, PostgreSQL63\Migration1554203706AddImportExportLog::class],
        [Core63\Migration1554900301AddReviewTable::class, 1554900301, PostgreSQL63\Migration1554900301AddReviewTable::class],
        [Core63\Migration1556809270AddAverageRatingToProduct::class, 1556809270, PostgreSQL63\Migration1556809270AddAverageRatingToProduct::class],
        [Core63\Migration1558082916AddBreadcrumb::class, 1558082916, PostgreSQL63\Migration1558082916AddBreadcrumb::class],
        [Core63\Migration1558105657CurrencyPrices::class, 1558105657, PostgreSQL63\Migration1558105657CurrencyPrices::class],
        [Core63\Migration1558443337PromotionSalesChannel::class, 1558443337, PostgreSQL63\Migration1558443337PromotionSalesChannel::class],
        [Core63\Migration1558505525Logging::class, 1558505525, PostgreSQL63\Migration1558505525Logging::class],
        [Core63\Migration1558594334PromotionDiscountPrice::class, 1558594334, PostgreSQL63\Migration1558594334PromotionDiscountPrice::class],
        [Core63\Migration1558938938ChangeGroupSortingColumn::class, 1558938938, PostgreSQL63\Migration1558938938ChangeGroupSortingColumn::class],
        [Core63\Migration1559050088Promotion::class, 1559050088, PostgreSQL63\Migration1559050088Promotion::class],
        [Core63\Migration1559050903PromotionExclusion::class, 1559050903, PostgreSQL63\Migration1559050903PromotionExclusion::class],
        [Core63\Migration1559134989Promotion::class, 1559134989, PostgreSQL63\Migration1559134989Promotion::class],
        [Core63\Migration1559306391PromotionIndividualCode::class, 1559306391, PostgreSQL63\Migration1559306391PromotionIndividualCode::class],
    ];

    /**
     * @var array<class-string<MigrationStep>, array{int, class-string<MigrationStep>}>
     */
    private array $variants = [];

    /**
     * @param iterable<array{class-string<MigrationStep>, int, class-string<MigrationStep>}> $variants
     */
    public function __construct(iterable $variants = [])
    {
        foreach ([self::CORE_VARIANTS, $variants] as $entries) {
            foreach ($entries as [$logicalClass, $timestamp, $implementation]) {
                $this->register($logicalClass, $timestamp, $implementation);
            }
        }
    }

    /**
     * Register the original class itself only after auditing it as portable.
     *
     * @param class-string<MigrationStep> $logicalClass
     * @param class-string<MigrationStep> $implementation
     */
    public function register(string $logicalClass, int $timestamp, string $implementation): void
    {
        if (isset($this->variants[$logicalClass]) || $this->isVariant($logicalClass)
            || ($implementation !== $logicalClass && (isset($this->variants[$implementation]) || $this->isVariant($implementation)))) {
            throw MigrationException::invalidArgument('Duplicate migration variant: ' . $logicalClass . ' -> ' . $implementation);
        }
        if ((new $logicalClass())->getPlausibleCreationTimestamp() !== $timestamp
            || (new $implementation())->getPlausibleCreationTimestamp() !== $timestamp) {
            throw MigrationException::invalidArgument('Migration variant timestamp mismatch: ' . $logicalClass . ' -> ' . $implementation);
        }
        $this->variants[$logicalClass] = [$timestamp, $implementation];
    }

    public function isVariant(string $class): bool
    {
        foreach ($this->variants as $logicalClass => [, $implementation]) {
            if ($class === $implementation && $class !== $logicalClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<MigrationStep> $logicalClass
     */
    public function resolve(string $logicalClass, int $timestamp): MigrationStep
    {
        $variant = $this->variants[$logicalClass] ?? null;
        if ($variant === null) {
            throw MigrationException::migrationError('No PostgreSQL migration variant for ' . $logicalClass . ' (timestamp ' . $timestamp . ').');
        }
        if ($variant[0] !== $timestamp) {
            throw MigrationException::migrationError('PostgreSQL migration history timestamp mismatch for ' . $logicalClass . '.');
        }

        $implementation = $variant[1];

        return new $implementation();
    }
}
