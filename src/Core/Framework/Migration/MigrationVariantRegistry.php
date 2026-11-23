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
        [Core63\Migration1561370284AddImportExportProductProfile::class, 1561370284, PostgreSQL63\Migration1561370284AddImportExportProductProfile::class],
        [Core63\Migration1561377793AddAvailableAsShippingCountry::class, 1561377793, PostgreSQL63\Migration1561377793AddAvailableAsShippingCountry::class],
        [Core63\Migration1561442979ElasticsearchIndexTask::class, 1561442979, Core63\Migration1561442979ElasticsearchIndexTask::class],
        [Core63\Migration1561452005Update::class, 1561452005, PostgreSQL63\Migration1561452005Update::class],
        [Core63\Migration1561712450NewSystemConfigsAndDefaultValues::class, 1561712450, PostgreSQL63\Migration1561712450NewSystemConfigsAndDefaultValues::class],
        [Core63\Migration1562228335SetConfigDefaults::class, 1562228335, PostgreSQL63\Migration1562228335SetConfigDefaults::class],
        [Core63\Migration1562240231UserPasswordRecovery::class, 1562240231, PostgreSQL63\Migration1562240231UserPasswordRecovery::class],
        [Core63\Migration1562306893MakeCustomerFirstLoginDateTime::class, 1562306893, PostgreSQL63\Migration1562306893MakeCustomerFirstLoginDateTime::class],
        [Core63\Migration1562324772AddOrderDateToOrder::class, 1562324772, PostgreSQL63\Migration1562324772AddOrderDateToOrder::class],
        [Core63\Migration1562579120ProductAvailableFields::class, 1562579120, PostgreSQL63\Migration1562579120ProductAvailableFields::class],
        [Core63\Migration1562684474AddDeliveryTime::class, 1562684474, PostgreSQL63\Migration1562684474AddDeliveryTime::class],
        [Core63\Migration1562841035AddProductChildCount::class, 1562841035, PostgreSQL63\Migration1562841035AddProductChildCount::class],
        [Core63\Migration1562933907ContactForm::class, 1562933907, PostgreSQL63\Migration1562933907ContactForm::class],
        [Core63\Migration1563180880AddDefaultThumbnailSizes::class, 1563180880, PostgreSQL63\Migration1563180880AddDefaultThumbnailSizes::class],
        [Core63\Migration1563288227MarkAsNewConfig::class, 1563288227, PostgreSQL63\Migration1563288227MarkAsNewConfig::class],
        [Core63\Migration1563518181PromotionDiscount::class, 1563518181, PostgreSQL63\Migration1563518181PromotionDiscount::class],
        [Core63\Migration1563805586AddLanguageToOrder::class, 1563805586, PostgreSQL63\Migration1563805586AddLanguageToOrder::class],
        [Core63\Migration1563949275AddCompanyToOrderCustomer::class, 1563949275, PostgreSQL63\Migration1563949275AddCompanyToOrderCustomer::class],
        [Core63\Migration1564475053RemoveSaveDocumentsConfig::class, 1564475053, Core63\Migration1564475053RemoveSaveDocumentsConfig::class],
        [Core63\Migration1565007156RemoveAutoIncrement::class, 1565007156, PostgreSQL63\Migration1565007156RemoveAutoIncrement::class],
        [Core63\Migration1565079228AddAclStructure::class, 1565079228, PostgreSQL63\Migration1565079228AddAclStructure::class],
        [Core63\Migration1565270155PromotionSetGroup::class, 1565270155, PostgreSQL63\Migration1565270155PromotionSetGroup::class],
        [Core63\Migration1565270366PromotionSetGroupRule::class, 1565270366, PostgreSQL63\Migration1565270366PromotionSetGroupRule::class],
        [Core63\Migration1565346846Promotion::class, 1565346846, PostgreSQL63\Migration1565346846Promotion::class],
        [Core63\Migration1565705280ProductExport::class, 1565705280, PostgreSQL63\Migration1565705280ProductExport::class],
        [Core63\Migration1566293076AddAutoIncrement::class, 1566293076, PostgreSQL63\Migration1566293076AddAutoIncrement::class],
        [Core63\Migration1566460168UpdateTexts::class, 1566460168, PostgreSQL63\Migration1566460168UpdateTexts::class],
        [Core63\Migration1566817701AddDisplayGroup::class, 1566817701, PostgreSQL63\Migration1566817701AddDisplayGroup::class],
        [Core63\Migration1567431050ContactFormTemplate::class, 1567431050, PostgreSQL63\Migration1567431050ContactFormTemplate::class],
        [Core63\Migration1568120239CmsSection::class, 1568120239, PostgreSQL63\Migration1568120239CmsSection::class],
        [Core63\Migration1568120302CmsBlockUpdate::class, 1568120302, PostgreSQL63\Migration1568120302CmsBlockUpdate::class],
        [Core63\Migration1568645037AddEnqueueDbal::class, 1568645037, PostgreSQL63\Migration1568645037AddEnqueueDbal::class],
        [Core63\Migration1568901713PromotionDiscount::class, 1568901713, PostgreSQL63\Migration1568901713PromotionDiscount::class],
        [Core63\Migration1569403146ProductVisibilityUnique::class, 1569403146, PostgreSQL63\Migration1569403146ProductVisibilityUnique::class],
        [Core63\Migration1570187167AddedAppConfig::class, 1570187167, PostgreSQL63\Migration1570187167AddedAppConfig::class],
        [Core63\Migration1570459127AddCmsSidebarLayout::class, 1570459127, PostgreSQL63\Migration1570459127AddCmsSidebarLayout::class],
        [Core63\Migration1570621541UpdateDefaultMailTemplates::class, 1570621541, PostgreSQL63\Migration1570621541UpdateDefaultMailTemplates::class],
        [Core63\Migration1570622696CustomerPasswordRecovery::class, 1570622696, PostgreSQL63\Migration1570622696CustomerPasswordRecovery::class],
        [Core63\Migration1570629862ClearCategoryBreadcrumbs::class, 1570629862, PostgreSQL63\Migration1570629862ClearCategoryBreadcrumbs::class],
        [Core63\Migration1570684913ScheduleIndexer::class, 1570684913, PostgreSQL63\Migration1570684913ScheduleIndexer::class],
        [Core63\Migration1571059598ChangeGreatBritainToUnitedKingdom::class, 1571059598, PostgreSQL63\Migration1571059598ChangeGreatBritainToUnitedKingdom::class],
        [Core63\Migration1571210820AddPaymentMethodIdsToSalesChannel::class, 1571210820, PostgreSQL63\Migration1571210820AddPaymentMethodIdsToSalesChannel::class],
        [Core63\Migration1571660203FixOrderDeliveryStateNames::class, 1571660203, PostgreSQL63\Migration1571660203FixOrderDeliveryStateNames::class],
        [Core63\Migration1571724915MultipleTrackingCodesInOrderDelivery::class, 1571724915, PostgreSQL63\Migration1571724915MultipleTrackingCodesInOrderDelivery::class],
        [Core63\Migration1571981437AddSeoColumns::class, 1571981437, PostgreSQL63\Migration1571981437AddSeoColumns::class],
        [Core63\Migration1571990395UpdateDefaultStatusMailTemplates::class, 1571990395, PostgreSQL63\Migration1571990395UpdateDefaultStatusMailTemplates::class],
        [Core63\Migration1572193798TaxRule::class, 1572193798, PostgreSQL63\Migration1572193798TaxRule::class],
        [Core63\Migration1572264837AddCacheId::class, 1572264837, PostgreSQL63\Migration1572264837AddCacheId::class],
        [Core63\Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType::class, 1572273565, PostgreSQL63\Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType::class],
        [Core63\Migration1572421282AddDoubleOptInRegistration::class, 1572421282, PostgreSQL63\Migration1572421282AddDoubleOptInRegistration::class],
        [Core63\Migration1572425108AddDoubleOptInRegistrationMailTemplate::class, 1572425108, PostgreSQL63\Migration1572425108AddDoubleOptInRegistrationMailTemplate::class],
        [Core63\Migration1572957455AddAffiliateTrackingColumns::class, 1572957455, PostgreSQL63\Migration1572957455AddAffiliateTrackingColumns::class],
        [Core63\Migration1573049297AddReopenTransitionToDeliveryStates::class, 1573049297, PostgreSQL63\Migration1573049297AddReopenTransitionToDeliveryStates::class],
        [Core63\Migration1573569685DoubleOptInGuestMailTemplate::class, 1573569685, PostgreSQL63\Migration1573569685DoubleOptInGuestMailTemplate::class],
        [Core63\Migration1573729158AddSitemapConfig::class, 1573729158, PostgreSQL63\Migration1573729158AddSitemapConfig::class],
        [Core63\Migration1574063550AddCurrencyToProductExport::class, 1574063550, PostgreSQL63\Migration1574063550AddCurrencyToProductExport::class],
        [Core63\Migration1574082635AddOrderLineItemProductId::class, 1574082635, PostgreSQL63\Migration1574082635AddOrderLineItemProductId::class],
        [Core63\Migration1574258787ProductSearchLanguageKey::class, 1574258787, PostgreSQL63\Migration1574258787ProductSearchLanguageKey::class],
        [Core63\Migration1574258788TaxRuleLanguageKey::class, 1574258788, PostgreSQL63\Migration1574258788TaxRuleLanguageKey::class],
        [Core63\Migration1574258789ProductReviewLanguageKey::class, 1574258789, PostgreSQL63\Migration1574258789ProductReviewLanguageKey::class],
        [Core63\Migration1574520220AddSalesChannelMaintenance::class, 1574520220, PostgreSQL63\Migration1574520220AddSalesChannelMaintenance::class],
        [Core63\Migration1574672450RemoteAddressIntoCustomerAndOrderCustomerTable::class, 1574672450, PostgreSQL63\Migration1574672450RemoteAddressIntoCustomerAndOrderCustomerTable::class],
        // These source classes use PostgreSQL-compatible SQL and DBAL operations.
        [Core63\Migration1575883959ResetListingPrices::class, 1575883959, Core63\Migration1575883959ResetListingPrices::class],
        [Core63\Migration1578470886FixPurchaseSteps::class, 1578470886, Core63\Migration1578470886FixPurchaseSteps::class],
        [Core63\Migration1578485775UseStableUpdateChannel::class, 1578485775, Core63\Migration1578485775UseStableUpdateChannel::class],
        [Core63\Migration1578648299ReindexSeoUrls::class, 1578648299, Core63\Migration1578648299ReindexSeoUrls::class],
        [Core63\Migration1588144801TriggerIndexer::class, 1588144801, Core63\Migration1588144801TriggerIndexer::class],
        [Core63\Migration1590566018RenameDefaultMediaFolders::class, 1590566018, Core63\Migration1590566018RenameDefaultMediaFolders::class],
    ];

    /**
     * Diagnostic-only best-effort V6_3 mappings generated by postgres-migration-translator.php.
     * They are not verified PostgreSQL ports; see dev-tools/database/POSTGRESQL_V63_DIAGNOSTIC.md.
     *
     * @var list<array{class-string<MigrationStep>, int, class-string<MigrationStep>}>
     */
    private const CORE_V63_DIAGNOSTIC_VARIANTS = [
        [Core63\Migration1574695657ProductCrossSelling::class, 1574695657, PostgreSQL63\Migration1574695657ProductCrossSelling::class],
        [Core63\Migration1574925962FixTaxConstraint::class, 1574925962, PostgreSQL63\Migration1574925962FixTaxConstraint::class],
        [Core63\Migration1575010262AddCmsFormLayouts::class, 1575010262, PostgreSQL63\Migration1575010262AddCmsFormLayouts::class],
        [Core63\Migration1575021466AddCurrencies::class, 1575021466, PostgreSQL63\Migration1575021466AddCurrencies::class],
        [Core63\Migration1575034234FixOrderDeliveryAddressConstraint::class, 1575034234, PostgreSQL63\Migration1575034234FixOrderDeliveryAddressConstraint::class],
        [Core63\Migration1575036586FixProductConfiguratorSettingsConstraint::class, 1575036586, PostgreSQL63\Migration1575036586FixProductConfiguratorSettingsConstraint::class],
        [Core63\Migration1575039284FixProductReviewConstraint::class, 1575039284, PostgreSQL63\Migration1575039284FixProductReviewConstraint::class],
        [Core63\Migration1575197543MailTemplateCustomFields::class, 1575197543, PostgreSQL63\Migration1575197543MailTemplateCustomFields::class],
        [Core63\Migration1575274700FixSalesChannelMailHeaderFooterConstraint::class, 1575274700, PostgreSQL63\Migration1575274700FixSalesChannelMailHeaderFooterConstraint::class],
        [Core63\Migration1575293069OrderMailTemplates::class, 1575293069, PostgreSQL63\Migration1575293069OrderMailTemplates::class],
        [Core63\Migration1575451283AddLimitToCrossSelling::class, 1575451283, PostgreSQL63\Migration1575451283AddLimitToCrossSelling::class],
        [Core63\Migration1575626180RemoveSearchKeywordInheritance::class, 1575626180, PostgreSQL63\Migration1575626180RemoveSearchKeywordInheritance::class],
        [Core63\Migration1576488398AddOrderLineItemPosition::class, 1576488398, PostgreSQL63\Migration1576488398AddOrderLineItemPosition::class],
        [Core63\Migration1576590301FixSalesChannelDomainLanguageFk::class, 1576590301, PostgreSQL63\Migration1576590301FixSalesChannelDomainLanguageFk::class],
        [Core63\Migration1578042218DefaultPages::class, 1578042218, PostgreSQL63\Migration1578042218DefaultPages::class],
        [Core63\Migration1578044453AddedNavigationDepth::class, 1578044453, PostgreSQL63\Migration1578044453AddedNavigationDepth::class],
        [Core63\Migration1578475268FixSloveneLocale::class, 1578475268, PostgreSQL63\Migration1578475268FixSloveneLocale::class],
        [Core63\Migration1578491480Hreflang::class, 1578491480, PostgreSQL63\Migration1578491480Hreflang::class],
        [Core63\Migration1578590702AddedPropertyGroupPosition::class, 1578590702, PostgreSQL63\Migration1578590702AddedPropertyGroupPosition::class],
        [Core63\Migration1578650334AddGoogleAnalyticsTable::class, 1578650334, PostgreSQL63\Migration1578650334AddGoogleAnalyticsTable::class],
        [Core63\Migration1580202210DefaultRule::class, 1580202210, PostgreSQL63\Migration1580202210DefaultRule::class],
        [Core63\Migration1580218617RefactorShippingMethodPrice::class, 1580218617, PostgreSQL63\Migration1580218617RefactorShippingMethodPrice::class],
        [Core63\Migration1580743279UpdateDeliveryMailTemplates::class, 1580743279, PostgreSQL63\Migration1580743279UpdateDeliveryMailTemplates::class],
        [Core63\Migration1580746806AddPaymentStates::class, 1580746806, PostgreSQL63\Migration1580746806AddPaymentStates::class],
        [Core63\Migration1580808849AddGermanContactFormTranslation::class, 1580808849, PostgreSQL63\Migration1580808849AddGermanContactFormTranslation::class],
        [Core63\Migration1580819350AddTrackingUrl::class, 1580819350, PostgreSQL63\Migration1580819350AddTrackingUrl::class],
        [Core63\Migration1580827023ProductCrossSellingAssignedProductsDefinition::class, 1580827023, PostgreSQL63\Migration1580827023ProductCrossSellingAssignedProductsDefinition::class],
        [Core63\Migration1582011195FixCountryStateGermanTranslation::class, 1582011195, PostgreSQL63\Migration1582011195FixCountryStateGermanTranslation::class],
        [Core63\Migration1583142266FixDefaultOrderConfirmationMailTemplateVATDisplay::class, 1583142266, PostgreSQL63\Migration1583142266FixDefaultOrderConfirmationMailTemplateVATDisplay::class],
        [Core63\Migration1583416186KeywordUniques::class, 1583416186, PostgreSQL63\Migration1583416186KeywordUniques::class],
        [Core63\Migration1583756864FixDeliveryForeignKey::class, 1583756864, PostgreSQL63\Migration1583756864FixDeliveryForeignKey::class],
        [Core63\Migration1583844433AddRefreshTokenTable::class, 1583844433, PostgreSQL63\Migration1583844433AddRefreshTokenTable::class],
        [Core63\Migration1584002637NewImportExport::class, 1584002637, PostgreSQL63\Migration1584002637NewImportExport::class],
        [Core63\Migration1584953715UpdateMailTemplatesAfterOrderLink::class, 1584953715, PostgreSQL63\Migration1584953715UpdateMailTemplatesAfterOrderLink::class],
        [Core63\Migration1585056571AddLanguageToMailTemplateMedia::class, 1585056571, PostgreSQL63\Migration1585056571AddLanguageToMailTemplateMedia::class],
        [Core63\Migration1585126355AddOrderCommentField::class, 1585126355, PostgreSQL63\Migration1585126355AddOrderCommentField::class],
        [Core63\Migration1585490020ActivateHoneypotCaptcha::class, 1585490020, PostgreSQL63\Migration1585490020ActivateHoneypotCaptcha::class],
        [Core63\Migration1585744384ChangeCategoryProfile::class, 1585744384, PostgreSQL63\Migration1585744384ChangeCategoryProfile::class],
        [Core63\Migration1585816139FixMediaMapping::class, 1585816139, PostgreSQL63\Migration1585816139FixMediaMapping::class],
        [Core63\Migration1586158920AddImportExportProfileConfig::class, 1586158920, PostgreSQL63\Migration1586158920AddImportExportProfileConfig::class],
        [Core63\Migration1586173614AddAdditionalImportExportProfiles::class, 1586173614, PostgreSQL63\Migration1586173614AddAdditionalImportExportProfiles::class],
        [Core63\Migration1586260286AddProductMainVariant::class, 1586260286, PostgreSQL63\Migration1586260286AddProductMainVariant::class],
        [Core63\Migration1586334003AddParentIdToProductProfile::class, 1586334003, PostgreSQL63\Migration1586334003AddParentIdToProductProfile::class],
        [Core63\Migration1587039363AddImportExportLabelField::class, 1587039363, PostgreSQL63\Migration1587039363AddImportExportLabelField::class],
        [Core63\Migration1587109484AddAfterOrderPaymentFlag::class, 1587109484, PostgreSQL63\Migration1587109484AddAfterOrderPaymentFlag::class],
        [Core63\Migration1587111506AddPausedScheduleToProductExport::class, 1587111506, PostgreSQL63\Migration1587111506AddPausedScheduleToProductExport::class],
        [Core63\Migration1587461582AddOpenToPaidTransition::class, 1587461582, PostgreSQL63\Migration1587461582AddOpenToPaidTransition::class],
        [Core63\Migration1588143272UpdateOrderStateChangeMailTemplates::class, 1588143272, PostgreSQL63\Migration1588143272UpdateOrderStateChangeMailTemplates::class],
        [Core63\Migration1588153272UpdateGermanMailTemplates::class, 1588153272, PostgreSQL63\Migration1588153272UpdateGermanMailTemplates::class],
        [Core63\Migration1589178550AddTaxCalculationType::class, 1589178550, PostgreSQL63\Migration1589178550AddTaxCalculationType::class],
        [Core63\Migration1589357321AddCountries::class, 1589357321, PostgreSQL63\Migration1589357321AddCountries::class],
        [Core63\Migration1589359936AddTaxCountryRules::class, 1589359936, PostgreSQL63\Migration1589359936AddTaxCountryRules::class],
        [Core63\Migration1589379060AddVariantCharacteristicsToEmailTemplates::class, 1589379060, PostgreSQL63\Migration1589379060AddVariantCharacteristicsToEmailTemplates::class],
        [Core63\Migration1589447332AddFilterableToPropertyGroup::class, 1589447332, PostgreSQL63\Migration1589447332AddFilterableToPropertyGroup::class],
        [Core63\Migration1589458026SetDefaultReviewConfig::class, 1589458026, PostgreSQL63\Migration1589458026SetDefaultReviewConfig::class],
        [Core63\Migration1590408550AclResources::class, 1590408550, PostgreSQL63\Migration1590408550AclResources::class],
        [Core63\Migration1590409548AddPackUnitPluralMigration::class, 1590409548, PostgreSQL63\Migration1590409548AddPackUnitPluralMigration::class],
        [Core63\Migration1590566405InvalidateSessionOnLogOut::class, 1590566405, PostgreSQL63\Migration1590566405InvalidateSessionOnLogOut::class],
        [Core63\Migration1590579986DropMailHeaderFooterConstraint::class, 1590579986, PostgreSQL63\Migration1590579986DropMailHeaderFooterConstraint::class],
        [Core63\Migration1590758953ProductFeatureSet::class, 1590758953, PostgreSQL63\Migration1590758953ProductFeatureSet::class],
        [Core63\Migration1591052278AddPropertyAndOptionIdsToProductProfile::class, 1591052278, PostgreSQL63\Migration1591052278AddPropertyAndOptionIdsToProductProfile::class],
        [Core63\Migration1591167126RoleDescription::class, 1591167126, PostgreSQL63\Migration1591167126RoleDescription::class],
        [Core63\Migration1591253089OrderDeeplinkForMailTemplates::class, 1591253089, PostgreSQL63\Migration1591253089OrderDeeplinkForMailTemplates::class],
        [Core63\Migration1591259559AddMissingCurrency::class, 1591259559, PostgreSQL63\Migration1591259559AddMissingCurrency::class],
        [Core63\Migration1591272594AddGoogleAnalyticsAnonymizeIpColumn::class, 1591272594, PostgreSQL63\Migration1591272594AddGoogleAnalyticsAnonymizeIpColumn::class],
        [Core63\Migration1591361320ChargebackAndAuthorized::class, 1591361320, PostgreSQL63\Migration1591361320ChargebackAndAuthorized::class],
        [Core63\Migration1591683158PromotionDiscount::class, 1591683158, PostgreSQL63\Migration1591683158PromotionDiscount::class],
        [Core63\Migration1591817370AddCustomerTagsManyToManyIdField::class, 1591817370, PostgreSQL63\Migration1591817370AddCustomerTagsManyToManyIdField::class],
        [Core63\Migration1592466717AddKeywordIndex::class, 1592466717, PostgreSQL63\Migration1592466717AddKeywordIndex::class],
        [Core63\Migration1592837424AddProductTypeToCategory::class, 1592837424, PostgreSQL63\Migration1592837424AddProductTypeToCategory::class],
        [Core63\Migration1592978289ProductCustomFieldSets::class, 1592978289, PostgreSQL63\Migration1592978289ProductCustomFieldSets::class],
        [Core63\Migration1593698606AddNetAndGrossPurchasePrices::class, 1593698606, PostgreSQL63\Migration1593698606AddNetAndGrossPurchasePrices::class],
        [Core63\Migration1594650256AddMailTemplateSalesChannelPK::class, 1594650256, PostgreSQL63\Migration1594650256AddMailTemplateSalesChannelPK::class],
        [Core63\Migration1594885630AddUserRecoveryPK::class, 1594885630, PostgreSQL63\Migration1594885630AddUserRecoveryPK::class],
        [Core63\Migration1594886106AddDocumentBaseConfigSalesChannelPK::class, 1594886106, PostgreSQL63\Migration1594886106AddDocumentBaseConfigSalesChannelPK::class],
        [Core63\Migration1594886773LogEntryPK::class, 1594886773, PostgreSQL63\Migration1594886773LogEntryPK::class],
        [Core63\Migration1594886895CustomerRecoveryPK::class, 1594886895, PostgreSQL63\Migration1594886895CustomerRecoveryPK::class],
        [Core63\Migration1594887027AppConfigPK::class, 1594887027, PostgreSQL63\Migration1594887027AppConfigPK::class],
        [Core63\Migration1595160327AddPositionToCustomFieldSet::class, 1595160327, PostgreSQL63\Migration1595160327AddPositionToCustomFieldSet::class],
        [Core63\Migration1595321666v3::class, 1595321666, PostgreSQL63\Migration1595321666v3::class],
        [Core63\Migration1595422169AddProductSorting::class, 1595422169, PostgreSQL63\Migration1595422169AddProductSorting::class],
        [Core63\Migration1595480600RemoveGoogleShoppingSalesChannel::class, 1595480600, PostgreSQL63\Migration1595480600RemoveGoogleShoppingSalesChannel::class],
        [Core63\Migration1595489705PreventOldListingPrices::class, 1595489705, PostgreSQL63\Migration1595489705PreventOldListingPrices::class],
        [Core63\Migration1595492052SeoUrl::class, 1595492052, PostgreSQL63\Migration1595492052SeoUrl::class],
        [Core63\Migration1595492053SeoUrlTemplate::class, 1595492053, PostgreSQL63\Migration1595492053SeoUrlTemplate::class],
        [Core63\Migration1595499689RemoveGoogleShoppingRelatedTables::class, 1595499689, PostgreSQL63\Migration1595499689RemoveGoogleShoppingRelatedTables::class],
        [Core63\Migration1595553089FixOrderConfirmationMailForAllPayloads::class, 1595553089, PostgreSQL63\Migration1595553089FixOrderConfirmationMailForAllPayloads::class],
        [Core63\Migration1595578253CustomFieldSetSelection::class, 1595578253, PostgreSQL63\Migration1595578253CustomFieldSetSelection::class],
        [Core63\Migration1595919251MainCategory::class, 1595919251, PostgreSQL63\Migration1595919251MainCategory::class],
        [Core63\Migration1596091744UseHomeAsRootCategoryName::class, 1596091744, PostgreSQL63\Migration1596091744UseHomeAsRootCategoryName::class],
        [Core63\Migration1596441551CustomerGroupRegistration::class, 1596441551, PostgreSQL63\Migration1596441551CustomerGroupRegistration::class],
        [Core63\Migration1597391970App::class, 1597391970, PostgreSQL63\Migration1597391970App::class],
        [Core63\Migration1597394241AddAppIdToCustomFieldSet::class, 1597394241, PostgreSQL63\Migration1597394241AddAppIdToCustomFieldSet::class],
        [Core63\Migration1597657689ActionButton::class, 1597657689, PostgreSQL63\Migration1597657689ActionButton::class],
        [Core63\Migration1597762808Webhook::class, 1597762808, PostgreSQL63\Migration1597762808Webhook::class],
        [Core63\Migration1597830237Template::class, 1597830237, PostgreSQL63\Migration1597830237Template::class],
        [Core63\Migration1597930227CustomerGroupRegistrationSalesChannel::class, 1597930227, PostgreSQL63\Migration1597930227CustomerGroupRegistrationSalesChannel::class],
        [Core63\Migration1598280548NullableSeoUrlTemplate::class, 1598280548, PostgreSQL63\Migration1598280548NullableSeoUrlTemplate::class],
        [Core63\Migration1598520424UpdateProductReviewConstraint::class, 1598520424, PostgreSQL63\Migration1598520424UpdateProductReviewConstraint::class],
        [Core63\Migration1598885384RedirectToLatestSeoUrl::class, 1598885384, PostgreSQL63\Migration1598885384RedirectToLatestSeoUrl::class],
        [Core63\Migration1599112309AddListingFilterSystemConfigOption::class, 1599112309, PostgreSQL63\Migration1599112309AddListingFilterSystemConfigOption::class],
        [Core63\Migration1599134496FixImportExportProfilesForGermanLanguage::class, 1599134496, PostgreSQL63\Migration1599134496FixImportExportProfilesForGermanLanguage::class],
        [Core63\Migration1599463278AddCustomerIdIntoSalesChannelContext::class, 1599463278, PostgreSQL63\Migration1599463278AddCustomerIdIntoSalesChannelContext::class],
        [Core63\Migration1599570560FixSlovakiaDisplayedAsSlovenia::class, 1599570560, PostgreSQL63\Migration1599570560FixSlovakiaDisplayedAsSlovenia::class],
        [Core63\Migration1599720163AddEventActiveColumn::class, 1599720163, PostgreSQL63\Migration1599720163AddEventActiveColumn::class],
        [Core63\Migration1599806584AddEventActionRule::class, 1599806584, PostgreSQL63\Migration1599806584AddEventActionRule::class],
        [Core63\Migration1599806595AddEventActionSalesChannel::class, 1599806595, PostgreSQL63\Migration1599806595AddEventActionSalesChannel::class],
        [Core63\Migration1599822061MigrateOrderMails::class, 1599822061, PostgreSQL63\Migration1599822061MigrateOrderMails::class],
        [Core63\Migration1600072779AddSearchKeywordsToProductTranslation::class, 1600072779, PostgreSQL63\Migration1600072779AddSearchKeywordsToProductTranslation::class],
        [Core63\Migration1600156989AddProductSalesField::class, 1600156989, PostgreSQL63\Migration1600156989AddProductSalesField::class],
        [Core63\Migration1600253602AddOrderRuleIds::class, 1600253602, PostgreSQL63\Migration1600253602AddOrderRuleIds::class],
        [Core63\Migration1600330846ChangeActiveColumn::class, 1600330846, PostgreSQL63\Migration1600330846ChangeActiveColumn::class],
        [Core63\Migration1600338271AddTopsellerSorting::class, 1600338271, PostgreSQL63\Migration1600338271AddTopsellerSorting::class],
        [Core63\Migration1600349343AddDeliveryStateTransitions::class, 1600349343, PostgreSQL63\Migration1600349343AddDeliveryStateTransitions::class],
        [Core63\Migration1600676671OrderLineItemCoverMedia::class, 1600676671, PostgreSQL63\Migration1600676671OrderLineItemCoverMedia::class],
        [Core63\Migration1600769357AddTransition::class, 1600769357, PostgreSQL63\Migration1600769357AddTransition::class],
        [Core63\Migration1600778848AddOrderMails::class, 1600778848, PostgreSQL63\Migration1600778848AddOrderMails::class],
        [Core63\Migration1601451838ChangeSearchKeywordColumnToProductTranslation::class, 1601451838, PostgreSQL63\Migration1601451838ChangeSearchKeywordColumnToProductTranslation::class],
        [Core63\Migration1601539530IntergrationRoleEntity::class, 1601539530, PostgreSQL63\Migration1601539530IntergrationRoleEntity::class],
        [Core63\Migration1601543829AddBoundSalesChannelIdColumnIntoCustomer::class, 1601543829, PostgreSQL63\Migration1601543829AddBoundSalesChannelIdColumnIntoCustomer::class],
        [Core63\Migration1601891339EventActionTitle::class, 1601891339, PostgreSQL63\Migration1601891339EventActionTitle::class],
        [Core63\Migration1602062376AddUniqueConstraintForEmailAndBoundSalesChannelIdIntoCustomerTable::class, 1602062376, PostgreSQL63\Migration1602062376AddUniqueConstraintForEmailAndBoundSalesChannelIdIntoCustomerTable::class],
        [Core63\Migration1602146868AddTaxTypeToShippingMethod::class, 1602146868, PostgreSQL63\Migration1602146868AddTaxTypeToShippingMethod::class],
        [Core63\Migration1602153572AddSalesChannelIdColumnIntoSalesChannelApiContextTable::class, 1602153572, PostgreSQL63\Migration1602153572AddSalesChannelIdColumnIntoSalesChannelApiContextTable::class],
        [Core63\Migration1602494493AddDefaultSettingConfigValueForWishlist::class, 1602494493, PostgreSQL63\Migration1602494493AddDefaultSettingConfigValueForWishlist::class],
        [Core63\Migration1602494495SetUsersAsAdmins::class, 1602494495, PostgreSQL63\Migration1602494495SetUsersAsAdmins::class],
        [Core63\Migration1602745374AddVatIdsColumnAndTransferVatIdFromCustomerAddressIntoCustomer::class, 1602745374, PostgreSQL63\Migration1602745374AddVatIdsColumnAndTransferVatIdFromCustomerAddressIntoCustomer::class],
        [Core63\Migration1602822727AddVatHandlingIntoCountryTable::class, 1602822727, PostgreSQL63\Migration1602822727AddVatHandlingIntoCountryTable::class],
        [Core63\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::class, 1603179023, PostgreSQL63\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::class],
        [Core63\Migration1603293043FixCurrencyTypo::class, 1603293043, PostgreSQL63\Migration1603293043FixCurrencyTypo::class],
        [Core63\Migration1603970276RemoveCustomerEmailUniqueConstraint::class, 1603970276, PostgreSQL63\Migration1603970276RemoveCustomerEmailUniqueConstraint::class],
        [Core63\Migration1604056363CustomerWishlist::class, 1604056363, PostgreSQL63\Migration1604056363CustomerWishlist::class],
        [Core63\Migration1604056421CustomerWishlistProducts::class, 1604056421, PostgreSQL63\Migration1604056421CustomerWishlistProducts::class],
        [Core63\Migration1604475913AddCMSPageIdToProductTable::class, 1604475913, PostgreSQL63\Migration1604475913AddCMSPageIdToProductTable::class],
        [Core63\Migration1604499476AddDefaultSettingConfigValueForContactForm::class, 1604499476, PostgreSQL63\Migration1604499476AddDefaultSettingConfigValueForContactForm::class],
        [Core63\Migration1604568928AddConfigurableToApp::class, 1604568928, PostgreSQL63\Migration1604568928AddConfigurableToApp::class],
        [Core63\Migration1604669773UpdateMailTemplate::class, 1604669773, PostgreSQL63\Migration1604669773UpdateMailTemplate::class],
        [Core63\Migration1605103533AddCookiesToAppEntity::class, 1605103533, PostgreSQL63\Migration1605103533AddCookiesToAppEntity::class],
        [Core63\Migration1605530777PrivacyPolicyExtensionsToAppTranslation::class, 1605530777, PostgreSQL63\Migration1605530777PrivacyPolicyExtensionsToAppTranslation::class],
        [Core63\Migration1605609309AddCreatedByIdAndUpdatedByIdToOrder::class, 1605609309, PostgreSQL63\Migration1605609309AddCreatedByIdAndUpdatedByIdToOrder::class],
        [Core63\Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTable::class, 1605609477, PostgreSQL63\Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTable::class],
        [Core63\Migration1605861407RuleAssociationsToRestrict::class, 1605861407, PostgreSQL63\Migration1605861407RuleAssociationsToRestrict::class],
        [Core63\Migration1606310257AddCanonicalUrlProp::class, 1606310257, PostgreSQL63\Migration1606310257AddCanonicalUrlProp::class],
        [Core63\Migration1607415095PromotionRedemptionsNullable::class, 1607415095, PostgreSQL63\Migration1607415095PromotionRedemptionsNullable::class],
        [Core63\Migration1607500561UpdateSignUpMailTemplateTranslation::class, 1607500561, PostgreSQL63\Migration1607500561UpdateSignUpMailTemplateTranslation::class],
        [Core63\Migration1607514878AddOrderDeliveryRetourTransition::class, 1607514878, PostgreSQL63\Migration1607514878AddOrderDeliveryRetourTransition::class],
        [Core63\Migration1607581275AddProductSearchConfiguration::class, 1607581275, PostgreSQL63\Migration1607581275AddProductSearchConfiguration::class],
        [Core63\Migration1607581276AddProductSearchConfigurationDefaults::class, 1607581276, PostgreSQL63\Migration1607581276AddProductSearchConfigurationDefaults::class],
        [Core63\Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent::class, 1608624028, PostgreSQL63\Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent::class],
        [Core63\Migration1609125144AddLastUpdatedPasswordAtColumnIntoUser::class, 1609125144, PostgreSQL63\Migration1609125144AddLastUpdatedPasswordAtColumnIntoUser::class],
        [Core63\Migration1609857999FixStateMachineHistoryUserConstraint::class, 1609857999, PostgreSQL63\Migration1609857999FixStateMachineHistoryUserConstraint::class],
        [Core63\Migration1610531188AddUserConfig::class, 1610531188, PostgreSQL63\Migration1610531188AddUserConfig::class],
        [Core63\Migration1610965670RemoveDeprecatedColumns::class, 1610965670, PostgreSQL63\Migration1610965670RemoveDeprecatedColumns::class],
        [Core63\Migration1610974673DropProductListingPriceTrigger::class, 1610974673, PostgreSQL63\Migration1610974673DropProductListingPriceTrigger::class],
        [Core63\Migration1611155140AddUpdatedAtToSalesChannelApiContext::class, 1611155140, PostgreSQL63\Migration1611155140AddUpdatedAtToSalesChannelApiContext::class],
        [Core63\Migration1614240671AddVatIdsToOrderCustomer::class, 1614240671, PostgreSQL63\Migration1614240671AddVatIdsToOrderCustomer::class],
    ];
    /**
     * Diagnostic-only best-effort V6_3 variants generated by postgres-migration-translator.php.
     * These mappings are not production ports; the committed diagnostic manifest records unhandled shapes.
     *
     * @var list<array{class-string<MigrationStep>, int, class-string<MigrationStep>}>
     */

    /**
     * @var array<class-string<MigrationStep>, array{int, class-string<MigrationStep>}>
     */
    private array $variants = [];

    /**
     * @param iterable<array{class-string<MigrationStep>, int, class-string<MigrationStep>}> $variants
     * @param bool $includeDiagnosticVariants Include unverified V6_3 mappings for diagnostic runs only.
     */
    public function __construct(iterable $variants = [], bool $includeDiagnosticVariants = false)
    {
        $variantSets = [self::CORE_VARIANTS];
        if ($includeDiagnosticVariants) {
            $variantSets[] = self::CORE_V63_DIAGNOSTIC_VARIANTS;
        }
        $variantSets[] = $variants;

        foreach ($variantSets as $entries) {
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
