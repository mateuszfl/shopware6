<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationVariantRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1552360944MediaFolderConfigurationNoAssoc;
use Shopware\Core\Migration\V6_3\Migration1554199340AddImportExportProfile;
use Shopware\Core\Migration\V6_3\Migration1554900301AddReviewTable;
use Shopware\Core\Migration\V6_3\Migration1556809270AddAverageRatingToProduct;
use Shopware\Core\Migration\V6_3\Migration1558082916AddBreadcrumb;
use Shopware\Core\Migration\V6_3\Migration1558105657CurrencyPrices;
use Shopware\Core\Migration\V6_3\Migration1558443337PromotionSalesChannel;
use Shopware\Core\Migration\V6_3\Migration1558505525Logging;
use Shopware\Core\Migration\V6_3\Migration1558938938ChangeGroupSortingColumn;
use Shopware\Core\Migration\V6_3\Migration1559050088Promotion;
use Shopware\Core\Migration\V6_3\Migration1559050903PromotionExclusion;
use Shopware\Core\Migration\V6_3\Migration1559134989Promotion;
use Shopware\Core\Migration\V6_3\Migration1559306391PromotionIndividualCode;
use Shopware\Core\Migration\V6_3\Migration1561370284AddImportExportProductProfile;
use Shopware\Core\Migration\V6_3\Migration1561377793AddAvailableAsShippingCountry;
use Shopware\Core\Migration\V6_3\Migration1561442979ElasticsearchIndexTask;
use Shopware\Core\Migration\V6_3\Migration1561452005Update;
use Shopware\Core\Migration\V6_3\Migration1561712450NewSystemConfigsAndDefaultValues;
use Shopware\Core\Migration\V6_3\Migration1562228335SetConfigDefaults;
use Shopware\Core\Migration\V6_3\Migration1562240231UserPasswordRecovery;
use Shopware\Core\Migration\V6_3\Migration1562306893MakeCustomerFirstLoginDateTime;
use Shopware\Core\Migration\V6_3\Migration1562324772AddOrderDateToOrder;
use Shopware\Core\Migration\V6_3\Migration1562579120ProductAvailableFields;
use Shopware\Core\Migration\V6_3\Migration1562684474AddDeliveryTime;
use Shopware\Core\Migration\V6_3\Migration1562841035AddProductChildCount;
use Shopware\Core\Migration\V6_3\Migration1562933907ContactForm;
use Shopware\Core\Migration\V6_3\Migration1563180880AddDefaultThumbnailSizes;
use Shopware\Core\Migration\V6_3\Migration1563288227MarkAsNewConfig;
use Shopware\Core\Migration\V6_3\Migration1563518181PromotionDiscount;
use Shopware\Core\Migration\V6_3\Migration1563805586AddLanguageToOrder;
use Shopware\Core\Migration\V6_3\Migration1563949275AddCompanyToOrderCustomer;
use Shopware\Core\Migration\V6_3\Migration1564475053RemoveSaveDocumentsConfig;
use Shopware\Core\Migration\V6_3\Migration1565007156RemoveAutoIncrement;
use Shopware\Core\Migration\V6_3\Migration1565079228AddAclStructure;
use Shopware\Core\Migration\V6_3\Migration1565270155PromotionSetGroup;
use Shopware\Core\Migration\V6_3\Migration1565270366PromotionSetGroupRule;
use Shopware\Core\Migration\V6_3\Migration1565346846Promotion;
use Shopware\Core\Migration\V6_3\Migration1565705280ProductExport;
use Shopware\Core\Migration\V6_3\Migration1566293076AddAutoIncrement;
use Shopware\Core\Migration\V6_3\Migration1566460168UpdateTexts;
use Shopware\Core\Migration\V6_3\Migration1566817701AddDisplayGroup;
use Shopware\Core\Migration\V6_3\Migration1567431050ContactFormTemplate;
use Shopware\Core\Migration\V6_3\Migration1568120239CmsSection;
use Shopware\Core\Migration\V6_3\Migration1568120302CmsBlockUpdate;
use Shopware\Core\Migration\V6_3\Migration1568645037AddEnqueueDbal;
use Shopware\Core\Migration\V6_3\Migration1568901713PromotionDiscount;
use Shopware\Core\Migration\V6_3\Migration1569403146ProductVisibilityUnique;
use Shopware\Core\Migration\V6_3\Migration1570187167AddedAppConfig;
use Shopware\Core\Migration\V6_3\Migration1570459127AddCmsSidebarLayout;
use Shopware\Core\Migration\V6_3\Migration1570621541UpdateDefaultMailTemplates;
use Shopware\Core\Migration\V6_3\Migration1570622696CustomerPasswordRecovery;
use Shopware\Core\Migration\V6_3\Migration1570629862ClearCategoryBreadcrumbs;
use Shopware\Core\Migration\V6_3\Migration1570684913ScheduleIndexer;
use Shopware\Core\Migration\V6_3\Migration1571059598ChangeGreatBritainToUnitedKingdom;
use Shopware\Core\Migration\V6_3\Migration1571210820AddPaymentMethodIdsToSalesChannel;
use Shopware\Core\Migration\V6_3\Migration1571660203FixOrderDeliveryStateNames;
use Shopware\Core\Migration\V6_3\Migration1571724915MultipleTrackingCodesInOrderDelivery;
use Shopware\Core\Migration\V6_3\Migration1571981437AddSeoColumns;
use Shopware\Core\Migration\V6_3\Migration1571990395UpdateDefaultStatusMailTemplates;
use Shopware\Core\Migration\V6_3\Migration1572193798TaxRule;
use Shopware\Core\Migration\V6_3\Migration1572264837AddCacheId;
use Shopware\Core\Migration\V6_3\Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType;
use Shopware\Core\Migration\V6_3\Migration1572421282AddDoubleOptInRegistration;
use Shopware\Core\Migration\V6_3\Migration1572425108AddDoubleOptInRegistrationMailTemplate;
use Shopware\Core\Migration\V6_3\Migration1572957455AddAffiliateTrackingColumns;

/**
 * @internal
 */
#[Package('framework')]
class PostInitialSchemaPostgreSQLTest extends MigrationHistoryDatabaseTestCase
{
    private const BASIC_DATA_TIMESTAMP = 1536233560;
    private const LAST_TIMESTAMP = 1572957455;

    public function testSchemaMatchesMySqlAfterEachUpgradeStepAndRetry(): void
    {
        $postgres = $this->collection($this->postgres);
        $mysql = $this->collection($this->mysql);
        foreach ([$postgres, $mysql] as $collection) {
            $collection->sync();
            static::assertCount(95, $collection->migrateInPlace(self::BASIC_DATA_TIMESTAMP));
        }

        $registry = new MigrationVariantRegistry();
        $pending = $mysql->getExecutableMigrations(self::LAST_TIMESTAMP);
        static::assertCount(68, $pending);
        static::assertSame($pending, $postgres->getExecutableMigrations(self::LAST_TIMESTAMP));
        foreach ($pending as $class) {
            $timestamp = (new $class())->getCreationTimestamp();
            static::assertSame([$class], $mysql->migrateInPlace($timestamp));
            static::assertSame([$class], $postgres->migrateInPlace($timestamp));
            $schema = $this->schema($this->postgres);
            static::assertSame($this->schema($this->mysql), $schema, $class);
            $registry->resolve($class, $timestamp)->update($this->postgres);
            static::assertSame($schema, $this->schema($this->postgres), $class);
        }

        $postgres->sync();
        static::assertSame([], $postgres->migrateInPlace(self::LAST_TIMESTAMP));
    }

    public function testDefaultMailTemplatesAreUpdatedOnceAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570459127);

            static::assertSame([Migration1570621541UpdateDefaultMailTemplates::class], $collection->migrateInPlace(1570621541));
            $contents = $connection->fetchFirstColumn(<<<'SQL'
                SELECT translation.content_plain
                FROM mail_template_translation AS translation
                INNER JOIN mail_template AS template ON template.id = translation.mail_template_id
                INNER JOIN mail_template_type AS type ON type.id = template.mail_template_type_id
                WHERE type.technical_name = :type AND template.system_default = 1
                ORDER BY translation.language_id
                SQL, ['type' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]);
            static::assertNotEmpty($contents);
            static::assertNotSame([], array_filter($contents, static fn (string $content): bool => str_contains($content, 'Thank you for your order') || str_contains($content, 'vielen Dank für Ihre Bestellung')));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1570621541UpdateDefaultMailTemplates::class, 1570621541);
                $variant->update($connection);
                static::assertSame(\count($contents), (int) $connection->fetchOne(<<<'SQL'
                    SELECT COUNT(*)
                    FROM mail_template_translation AS translation
                    INNER JOIN mail_template AS template ON template.id = translation.mail_template_id
                    INNER JOIN mail_template_type AS type ON type.id = template.mail_template_type_id
                    WHERE type.technical_name = :type AND template.system_default = 1
                    SQL, ['type' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]));
                static::assertSame($contents[0], $connection->fetchOne(<<<'SQL'
                    SELECT translation.content_plain
                    FROM mail_template_translation AS translation
                    INNER JOIN mail_template AS template ON template.id = translation.mail_template_id
                    INNER JOIN mail_template_type AS type ON type.id = template.mail_template_type_id
                    WHERE type.technical_name = :type AND template.system_default = 1
                    ORDER BY translation.language_id
                    LIMIT 1
                    SQL, ['type' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]));
            }
        }
    }

    public function testCustomerPasswordRecoveryIsSeededOnceAndRetryPreservesIt(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570621541);

            static::assertSame([Migration1570622696CustomerPasswordRecovery::class], $collection->migrateInPlace(1570622696));
            static::assertArrayHasKey('customer_recovery', $this->schema($connection));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = \'customer.recovery.request\''));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'customer.recovery.request\')'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = \'customer.recovery.request\''));
        }

        $variant = (new MigrationVariantRegistry())->resolve(Migration1570622696CustomerPasswordRecovery::class, 1570622696);
        $variant->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = \'customer.recovery.request\''));
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'customer.recovery.request\')'));
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = \'customer.recovery.request\''));
    }

    public function testCategoryBreadcrumbsAreClearedAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570622696);

            static::assertSame([Migration1570629862ClearCategoryBreadcrumbs::class], $collection->migrateInPlace(1570629862));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM category_translation WHERE breadcrumb IS NOT NULL'));
        }

        $variant = (new MigrationVariantRegistry())->resolve(Migration1570629862ClearCategoryBreadcrumbs::class, 1570629862);
        $variant->update($this->postgres);
        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM category_translation WHERE breadcrumb IS NOT NULL'));
    }

    public function testScheduledIndexersAreRegisteredOnce(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570629862);

            static::assertSame([Migration1570684913ScheduleIndexer::class], $collection->migrateInPlace(1570684913));
            $value = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.scheduled_indexers\' AND sales_channel_id IS NULL');
            static::assertIsString($value);
            $indexers = json_decode($value, true, 512, \JSON_THROW_ON_ERROR)['_value'];
            static::assertArrayHasKey('Swag.BreadcrumbIndexer', $indexers);
            static::assertArrayHasKey('Swag.VariantListingIndexer', $indexers);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1570684913ScheduleIndexer::class, 1570684913);
                $variant->update($connection);
                static::assertSame($indexers, json_decode((string) $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.scheduled_indexers\' AND sales_channel_id IS NULL'), true, 512, \JSON_THROW_ON_ERROR)['_value']);
            }
        }
    }

    public function testCountryNamesAreUpdatedAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570684913);

            static::assertSame([Migration1571059598ChangeGreatBritainToUnitedKingdom::class], $collection->migrateInPlace(1571059598));
            $names = $connection->fetchFirstColumn('SELECT name FROM country_translation WHERE name IN (\'United Kingdom\', \'Vereinigtes Königreich\') ORDER BY name');
            static::assertContains('United Kingdom', $names);
            static::assertContains('Vereinigtes Königreich', $names);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571059598ChangeGreatBritainToUnitedKingdom::class, 1571059598);
                $variant->update($connection);
                static::assertSame($names, $connection->fetchFirstColumn('SELECT name FROM country_translation WHERE name IN (\'United Kingdom\', \'Vereinigtes Königreich\') ORDER BY name'));
            }
        }
    }

    public function testSalesChannelPaymentMethodsAreAddedAndIndexerRegistered(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571059598);

            static::assertSame([Migration1571210820AddPaymentMethodIdsToSalesChannel::class], $collection->migrateInPlace(1571210820));
            $columns = array_column($this->columns($connection)['sales_channel'], null, 'name');
            static::assertArrayHasKey('payment_method_ids', $columns);
            static::assertSame('YES', $columns['payment_method_ids']['nullable']);
            $indexerConfig = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.scheduled_indexers\' AND sales_channel_id IS NULL');
            static::assertIsString($indexerConfig);
            static::assertArrayHasKey('sales_channel.indexer', json_decode($indexerConfig, true, 512, \JSON_THROW_ON_ERROR)['_value']);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571210820AddPaymentMethodIdsToSalesChannel::class, 1571210820);
                $schema = $this->schema($connection)['sales_channel'];
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection)['sales_channel']);
                static::assertSame($indexerConfig, $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.scheduled_indexers\' AND sales_channel_id IS NULL'));
            }
        }
    }

    public function testOrderDeliveryMailTypeNamesAreUpdatedAndRetrySafely(): void
    {
        $type = 'state_enter.order_delivery.state.shipped';
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571210820);

            static::assertSame([Migration1571660203FixOrderDeliveryStateNames::class], $collection->migrateInPlace(1571660203));
            static::assertSame('Enter delivery state: Shipped', $connection->fetchOne("SELECT translation.name FROM mail_template_type_translation AS translation INNER JOIN mail_template_type AS template_type ON template_type.id = translation.mail_template_type_id INNER JOIN language ON language.id = translation.language_id INNER JOIN locale ON locale.id = language.locale_id WHERE template_type.technical_name = '$type' AND locale.code = 'en-GB'"));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571660203FixOrderDeliveryStateNames::class, 1571660203);
                $variant->update($connection);
                static::assertSame('Enter delivery state: Shipped', $connection->fetchOne("SELECT translation.name FROM mail_template_type_translation AS translation INNER JOIN mail_template_type AS template_type ON template_type.id = translation.mail_template_type_id INNER JOIN language ON language.id = translation.language_id INNER JOIN locale ON locale.id = language.locale_id WHERE template_type.technical_name = '$type' AND locale.code = 'en-GB'"));
            }
        }
    }

    public function testOrderDeliveryTrackingCodesAreMigratedAndLegacyColumnIsDropped(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571660203);
            $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
            $currencyId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM currency LIMIT 1'));
            $languageId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM language LIMIT 1'));
            $salesChannelId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'));
            $shippingMethodId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM shipping_method LIMIT 1'));
            static::assertIsString($currencyId);
            static::assertIsString($languageId);
            static::assertIsString($salesChannelId);
            static::assertIsString($shippingMethodId);
            $orderId = Uuid::randomBytes();
            $deliveryId = Uuid::randomBytes();
            $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $connection->executeStatement(
                'INSERT INTO ' . $connection->getDatabasePlatform()->quoteSingleIdentifier('order') . ' (id, version_id, state_id, currency_id, language_id, sales_channel_id, billing_address_id, billing_address_version_id, price, order_date_time, shipping_costs, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $versionId, Uuid::randomBytes(), $currencyId, $languageId, $salesChannelId, Uuid::randomBytes(), $versionId, '{"totalPrice":1,"netPrice":1,"positionPrice":1,"taxStatus":"gross"}', $createdAt, '{"totalPrice":0}', $createdAt],
                [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]
            );
            $connection->insert('order_delivery', [
                'id' => $deliveryId,
                'version_id' => $versionId,
                'order_id' => $orderId,
                'order_version_id' => $versionId,
                'state_id' => Uuid::randomBytes(),
                'shipping_method_id' => $shippingMethodId,
                'shipping_date_earliest' => '2020-01-02',
                'shipping_date_latest' => '2020-01-03',
                'shipping_costs' => '{"totalPrice":0}',
                'tracking_code' => 'TRACKING-TEST',
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

            static::assertSame([Migration1571724915MultipleTrackingCodesInOrderDelivery::class], $collection->migrateInPlace(1571724915));
            $columns = array_column($this->columns($connection)['order_delivery'], null, 'name');
            static::assertArrayHasKey('tracking_codes', $columns);
            static::assertSame('NO', $columns['tracking_codes']['nullable']);
            static::assertArrayHasKey('tracking_code', $columns);
            $codes = $connection->fetchOne('SELECT tracking_codes FROM order_delivery WHERE id = ?', [$deliveryId], [ParameterType::BINARY]);
            static::assertSame(['TRACKING-TEST'], json_decode((string) $codes, true, 512, \JSON_THROW_ON_ERROR));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571724915MultipleTrackingCodesInOrderDelivery::class, 1571724915);
                $variant->update($connection);
                $variant->updateDestructive($connection);
                $variant->updateDestructive($connection);
            } else {
                (new Migration1571724915MultipleTrackingCodesInOrderDelivery())->updateDestructive($connection);
            }

            $columns = array_column($this->columns($connection)['order_delivery'], null, 'name');
            static::assertArrayNotHasKey('tracking_code', $columns);
            $codes = $connection->fetchOne('SELECT tracking_codes FROM order_delivery WHERE id = ?', [$deliveryId], [ParameterType::BINARY]);
            static::assertSame(['TRACKING-TEST'], json_decode((string) $codes, true, 512, \JSON_THROW_ON_ERROR));
        }
    }

    public function testSeoColumnsCopyDataBeforeDestructiveDropAndRetrySafely(): void
    {
        $mysqlSchema = null;
        $postgresSchema = null;
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571724915);

            $productId = Uuid::randomBytes();
            $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
            $languageId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM language LIMIT 1'));
            static::assertIsString($languageId);
            $connection->insert('product', [
                'id' => $productId,
                'version_id' => $versionId,
                'stock' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
            $connection->insert('product_translation', [
                'product_id' => $productId,
                'product_version_id' => $versionId,
                'language_id' => $languageId,
                'additional_text' => 'SEO-TEST-DATA',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);

            static::assertSame([Migration1571981437AddSeoColumns::class], $collection->migrateInPlace(1571981437));
            static::assertSame('SEO-TEST-DATA', $connection->fetchOne('SELECT meta_description FROM product_translation WHERE product_id = ? AND product_version_id = ? AND language_id = ?', [$productId, $versionId, $languageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
            $productColumns = array_column($this->columns($connection)['product_translation'], null, 'name');
            $categoryColumns = array_column($this->columns($connection)['category_translation'], null, 'name');
            static::assertArrayHasKey('meta_description', $productColumns);
            static::assertArrayHasKey('meta_title', $categoryColumns);
            static::assertArrayHasKey('meta_description', $categoryColumns);
            static::assertArrayHasKey('keywords', $categoryColumns);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571981437AddSeoColumns::class, 1571981437);
                $variant->update($connection);
                static::assertSame('SEO-TEST-DATA', $connection->fetchOne('SELECT meta_description FROM product_translation WHERE product_id = ? AND product_version_id = ? AND language_id = ?', [$productId, $versionId, $languageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
                $variant->updateDestructive($connection);
                $variant->updateDestructive($connection);
                $variant->update($connection);
                static::assertSame('SEO-TEST-DATA', $connection->fetchOne('SELECT meta_description FROM product_translation WHERE product_id = ? AND product_version_id = ? AND language_id = ?', [$productId, $versionId, $languageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
            } else {
                (new Migration1571981437AddSeoColumns())->updateDestructive($connection);
            }

            $productColumns = array_column($this->columns($connection)['product_translation'], null, 'name');
            static::assertArrayNotHasKey('additional_text', $productColumns);
            static::assertSame('SEO-TEST-DATA', $connection->fetchOne('SELECT meta_description FROM product_translation WHERE product_id = ? AND product_version_id = ? AND language_id = ?', [$productId, $versionId, $languageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
            $schema = [
                $this->schema($connection)['product_translation'],
                $this->schema($connection)['category_translation'],
            ];
            if ($connection === $this->mysql) {
                $mysqlSchema = $schema;
            } else {
                $postgresSchema = $schema;
            }
        }

        static::assertNotNull($mysqlSchema);
        static::assertNotNull($postgresSchema);
        static::assertSame($mysqlSchema, $postgresSchema);
    }

    public function testDefaultStatusMailTemplatesAreUpdatedAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571981437);

            static::assertSame([Migration1571990395UpdateDefaultStatusMailTemplates::class], $collection->migrateInPlace(1571990395));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = \'order_delivery.state.shipped\''));
            $subject = $connection->fetchOne(<<<'SQL'
                SELECT translation.subject
                FROM mail_template_translation AS translation
                INNER JOIN mail_template AS template ON template.id = translation.mail_template_id
                INNER JOIN mail_template_type AS template_type ON template_type.id = template.mail_template_type_id
                INNER JOIN language ON language.id = translation.language_id
                INNER JOIN locale ON locale.id = language.locale_id
                WHERE template_type.technical_name = :type AND locale.code = 'en-GB' AND template.system_default = 1
                ORDER BY template.created_at DESC
                LIMIT 1
                SQL, ['type' => 'order_delivery.state.shipped']);
            static::assertSame('Your order with {{ salesChannel.name }} is delivered', $subject);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1571990395UpdateDefaultStatusMailTemplates::class, 1571990395);
                $templateCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'order_delivery.state.shipped\') AND system_default = 1');
                $variant->update($connection);
                static::assertSame($templateCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'order_delivery.state.shipped\') AND system_default = 1'));
                static::assertSame($subject, $connection->fetchOne(<<<'SQL'
                    SELECT translation.subject
                    FROM mail_template_translation AS translation
                    INNER JOIN mail_template AS template ON template.id = translation.mail_template_id
                    INNER JOIN mail_template_type AS template_type ON template_type.id = template.mail_template_type_id
                    INNER JOIN language ON language.id = translation.language_id
                    INNER JOIN locale ON locale.id = language.locale_id
                    WHERE template_type.technical_name = :type AND locale.code = 'en-GB' AND template.system_default = 1
                    ORDER BY template.created_at DESC
                    LIMIT 1
                    SQL, ['type' => 'order_delivery.state.shipped']));
            }
        }
    }

    public function testTaxRuleTablesAndSeedTypesMatchMySqlAndRetrySafely(): void
    {
        $mysqlSchemas = [];
        $postgresSchemas = [];
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1571990395);

            static::assertSame([Migration1572193798TaxRule::class], $collection->migrateInPlace(1572193798));
            static::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax_rule_type'));
            static::assertGreaterThanOrEqual(8, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax_rule_type_translation'));

            $taxId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM tax LIMIT 1'));
            $countryId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM country LIMIT 1'));
            $ruleTypeId = $this->normalizeBinaryValue($connection->fetchOne('SELECT id FROM tax_rule_type WHERE technical_name = :name', ['name' => 'zip_code']));
            static::assertIsString($taxId);
            static::assertIsString($countryId);
            static::assertIsString($ruleTypeId);
            $connection->insert('tax_rule', [
                'id' => Uuid::randomBytes(),
                'tax_id' => $taxId,
                'tax_rule_type_id' => $ruleTypeId,
                'country_id' => $countryId,
                'tax_rate' => 19.0,
                'data' => '{"zipCode":"12345"}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
            $taxRuleData = $connection->fetchOne('SELECT data FROM tax_rule WHERE tax_rate = 19');
            static::assertSame(['zipCode' => '12345'], json_decode((string) $taxRuleData, true, 512, \JSON_THROW_ON_ERROR));

            $schema = [
                $this->schema($connection)['tax_rule_type'],
                $this->schema($connection)['tax_rule_type_translation'],
                $this->schema($connection)['tax_rule'],
            ];
            if ($connection === $this->mysql) {
                $mysqlSchemas = $schema;
            } else {
                $postgresSchemas = $schema;
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572193798TaxRule::class, 1572193798);
                $variant->update($connection);
                static::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax_rule_type'));
                static::assertGreaterThanOrEqual(8, (int) $connection->fetchOne('SELECT COUNT(*) FROM tax_rule_type_translation'));
                static::assertSame($schema, [
                    $this->schema($connection)['tax_rule_type'],
                    $this->schema($connection)['tax_rule_type_translation'],
                    $this->schema($connection)['tax_rule'],
                ]);
            }
        }

        static::assertSame($mysqlSchemas, $postgresSchemas);
    }

    public function testAppConfigIsResetAndCacheIdIsRecreated(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1572193798);
            $keyColumn = $connection->getDatabasePlatform()->quoteSingleIdentifier('key');
            $connection->executeStatement("INSERT INTO app_config ($keyColumn, value) VALUES (:key, :value)", [
                'key' => 'stale-setting',
                'value' => 'stale-value',
            ]);

            static::assertSame([Migration1572264837AddCacheId::class], $collection->migrateInPlace(1572264837));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_config'));
            static::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM app_config WHERE $keyColumn = :key", ['key' => 'cache-id']));
            static::assertSame(0, (int) $connection->fetchOne("SELECT COUNT(*) FROM app_config WHERE $keyColumn = :key", ['key' => 'stale-setting']));
            static::assertSame(['key'], $this->schema($connection)['app_config']['primaryKey']);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572264837AddCacheId::class, 1572264837);
                $variant->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_config'));
                static::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM app_config WHERE $keyColumn = :key", ['key' => 'cache-id']));
            }
        }
    }

    public function testDuplicateDocumentTypesAreRemovedBeforeUniqueConstraintIsAdded(): void
    {
        $schemas = [];
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1572264837);

            $oldId = Uuid::randomBytes();
            $newId = Uuid::randomBytes();
            $documentTypes = $connection === $this->postgres
                ? [[$oldId, '2020-01-01 00:00:00.000'], [$newId, '2021-01-01 00:00:00.000']]
                : [[$oldId, '2020-01-01 00:00:00.000']];
            foreach ($documentTypes as [$id, $createdAt]) {
                $connection->insert('document_type', [
                    'id' => $id,
                    'technical_name' => 'migration.test.duplicate',
                    'created_at' => $createdAt,
                ], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
            }

            static::assertSame([Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType::class], $collection->migrateInPlace(1572273565));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM document_type WHERE technical_name = :name', ['name' => 'migration.test.duplicate']));

            $schema = $this->schema($connection)['document_type'];
            $schemas[$connection === $this->mysql ? 'mysql' : 'postgres'] = $schema;
            if ($connection === $this->postgres) {
                static::assertStringStartsWith('2020-01-01 00:00:00', (string) $connection->fetchOne('SELECT created_at FROM document_type WHERE technical_name = :name', ['name' => 'migration.test.duplicate']));
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType::class, 1572273565);
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection)['document_type']);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM document_type WHERE technical_name = :name', ['name' => 'migration.test.duplicate']));
            }
        }

        static::assertArrayHasKey('mysql', $schemas);
        static::assertArrayHasKey('postgres', $schemas);
        static::assertSame($schemas['mysql'], $schemas['postgres']);
    }

    public function testCustomerDoubleOptInColumnsAndUniqueHashMatchMySql(): void
    {
        $schemas = [];
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1572273565);

            static::assertSame([Migration1572421282AddDoubleOptInRegistration::class], $collection->migrateInPlace(1572421282));
            $schema = $this->schema($connection)['customer'];
            $schemas[$connection === $this->mysql ? 'mysql' : 'postgres'] = $schema;
            $columns = array_column($schema['columns'], null, 'name');
            static::assertArrayHasKey('doubleOptInRegistration', $columns);
            static::assertArrayHasKey('doubleOptInEmailSentDate', $columns);
            static::assertArrayHasKey('doubleOptInConfirmDate', $columns);
            static::assertArrayHasKey('hash', $columns);
            $hashIndexes = array_filter($schema['indexes'], static fn (array $index): bool => $index[1] === ['hash']);
            static::assertNotEmpty($hashIndexes);
            static::assertSame('UNIQUE', array_values($hashIndexes)[0][0]);

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572421282AddDoubleOptInRegistration::class, 1572421282);
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection)['customer']);
            }
        }

        static::assertArrayHasKey('mysql', $schemas);
        static::assertArrayHasKey('postgres', $schemas);
        static::assertSame($schemas['mysql'], $schemas['postgres']);
    }

    public function testDoubleOptInRegistrationMailTemplateIsCreatedAndRetrySafe(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1572421282);

            static::assertSame([Migration1572425108AddDoubleOptInRegistrationMailTemplate::class], $collection->migrateInPlace(1572425108));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = ?', ['customer_register.double_opt_in']));
            $typeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = ?', ['customer_register.double_opt_in']);
            if (\is_resource($typeId)) {
                $typeId = stream_get_contents($typeId);
            }
            static::assertIsString($typeId);
            $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = ?', [$typeId], [ParameterType::BINARY]);
            if (\is_resource($templateId)) {
                $templateId = stream_get_contents($templateId);
            }
            static::assertIsString($templateId);
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type_translation WHERE mail_template_type_id = ?', [$typeId], [ParameterType::BINARY]));
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation WHERE mail_template_id = ?', [$templateId], [ParameterType::BINARY]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = ? AND action_name = ?', [CustomerDoubleOptInRegistrationEvent::EVENT_NAME, SendMailAction::ACTION_NAME]));
            static::assertSame('Please confirm your sign-up with {{ salesChannel.translated.name }}', $connection->fetchOne('SELECT subject FROM mail_template_translation WHERE mail_template_id = ? AND language_id = ?', [$templateId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572425108AddDoubleOptInRegistrationMailTemplate::class, 1572425108);
                $variant->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = ?', ['customer_register.double_opt_in']));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = ?', [$typeId], [ParameterType::BINARY]));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = ? AND action_name = ?', [CustomerDoubleOptInRegistrationEvent::EVENT_NAME, SendMailAction::ACTION_NAME]));
                static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation WHERE mail_template_id = ?', [$templateId], [ParameterType::BINARY]));
            }
        }
    }

    public function testAffiliateTrackingColumnsMatchMySqlAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1572425108);

            static::assertSame([Migration1572957455AddAffiliateTrackingColumns::class], $collection->migrateInPlace(1572957455));
            foreach (['customer', 'order'] as $table) {
                $columns = array_column($this->columns($connection)[$table], null, 'name');
                static::assertArrayHasKey('affiliate_code', $columns);
                static::assertArrayHasKey('campaign_code', $columns);
                static::assertSame('YES', $columns['affiliate_code']['nullable']);
                static::assertSame('YES', $columns['campaign_code']['nullable']);
            }

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection);
                $variant = (new MigrationVariantRegistry())->resolve(Migration1572957455AddAffiliateTrackingColumns::class, 1572957455);
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection));
            }
        }

        static::assertSame($this->schema($this->mysql)['customer'], $this->schema($this->postgres)['customer']);
        static::assertSame($this->schema($this->mysql)['order'], $this->schema($this->postgres)['order']);
    }

    public function testCmsSectionDefaultsAndIdempotentCreation(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1567431050);

            static::assertSame([Migration1568120239CmsSection::class], $collection->migrateInPlace(1568120239));
            $sectionColumns = array_column($this->columns($connection)['cms_section'], null, 'name');
            static::assertSame('default', $sectionColumns['type']['default_value']);
            static::assertSame('boxed', $sectionColumns['sizing_mode']['default_value']);
            static::assertSame('wrap', $sectionColumns['mobile_behavior']['default_value']);
            static::assertSame('0', (string) $sectionColumns['locked']['default_value']);

            $pageId = $connection->fetchOne('SELECT id FROM cms_page LIMIT 1');
            if (\is_resource($pageId)) {
                $pageId = stream_get_contents($pageId);
            }
            static::assertIsString($pageId);
            $sectionId = Uuid::randomBytes();
            $connection->insert('cms_section', [
                'id' => $sectionId,
                'cms_page_id' => $pageId,
                'position' => 9,
                'created_at' => '2020-01-01 00:00:00.123',
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
            static::assertSame('default', $connection->fetchOne('SELECT type FROM cms_section WHERE id = ?', [$sectionId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT locked FROM cms_section WHERE id = ?', [$sectionId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['cms_section'];
                (new MigrationVariantRegistry())->resolve(Migration1568120239CmsSection::class, 1568120239)->update($connection);
                static::assertSame($schema, $this->schema($connection)['cms_section']);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section WHERE id = ?', [$sectionId], [ParameterType::BINARY]));
            }
        }
    }

    public function testCmsBlocksMoveToSectionsAndDestructiveColumnsAreRemoved(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1568120239);
            $collection->migrateDestructiveInPlace(1568120239);
            $initialBlockCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block');

            static::assertSame([Migration1568120302CmsBlockUpdate::class], $collection->migrateInPlace(1568120302));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block WHERE cms_section_id IS NULL'));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block INNER JOIN cms_section ON cms_section.id = cms_block.cms_section_id WHERE cms_block.cms_page_id <> cms_section.cms_page_id'));
            static::assertSame('main', $connection->fetchOne('SELECT section_position FROM cms_block LIMIT 1'));
            static::assertSame($initialBlockCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block'));

            if ($connection === $this->postgres) {
                $sectionCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section');
                $assignments = $connection->fetchAllAssociative('SELECT encode(id, \'hex\') AS id, encode(cms_section_id, \'hex\') AS cms_section_id FROM cms_block ORDER BY id');
                $variant = (new MigrationVariantRegistry())->resolve(Migration1568120302CmsBlockUpdate::class, 1568120302);
                $schema = $this->schema($connection)['cms_block'];
                $variant->update($connection);
                static::assertSame($sectionCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section'));
                static::assertSame($assignments, $connection->fetchAllAssociative('SELECT encode(id, \'hex\') AS id, encode(cms_section_id, \'hex\') AS cms_section_id FROM cms_block ORDER BY id'));
                static::assertSame($schema, $this->schema($connection)['cms_block']);
            }

            static::assertSame([Migration1568120302CmsBlockUpdate::class], $collection->migrateDestructiveInPlace(1568120302));
            static::assertNotContains('cms_page_id', array_column($this->columns($connection)['cms_block'], 'name'));
            static::assertNotContains('sizing_mode', array_column($this->columns($connection)['cms_block'], 'name'));
            static::assertSame($initialBlockCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block'));
            static::assertSame($initialBlockCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block WHERE cms_section_id IS NOT NULL'));

            if ($connection === $this->postgres) {
                (new MigrationVariantRegistry())->resolve(Migration1568120302CmsBlockUpdate::class, 1568120302)->updateDestructive($connection);
                static::assertNotContains('cms_page_id', array_column($this->columns($connection)['cms_block'], 'name'));
                static::assertNotContains('sizing_mode', array_column($this->columns($connection)['cms_block'], 'name'));
                static::assertSame($initialBlockCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block'));
            }
        }
    }

    public function testEnqueueTableAndIndexesAreCreatedIdempotently(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1568120302);

            static::assertSame([Migration1568645037AddEnqueueDbal::class], $collection->migrateInPlace(1568645037));
            $connection->insert('enqueue', ['id' => '2be6a33c-0000-0000-0000-000000000001', 'published_at' => 1570000000, 'queue' => 'async']);
            static::assertSame('async', $connection->fetchOne('SELECT queue FROM enqueue WHERE id = ?', ['2be6a33c-0000-0000-0000-000000000001']));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['enqueue'];
                (new MigrationVariantRegistry())->resolve(Migration1568645037AddEnqueueDbal::class, 1568645037)->update($connection);
                static::assertSame($schema, $this->schema($connection)['enqueue']);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM enqueue'));
            }
        }
    }

    public function testPromotionDiscountKeysAreAddedWithoutChangingExistingRows(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1568645037);
            $existingCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount');

            static::assertSame([Migration1568901713PromotionDiscount::class], $collection->migrateInPlace(1568901713));
            $discountColumns = array_column($this->columns($connection)['promotion_discount'], null, 'name');
            foreach (['sorter_key', 'applier_key', 'usage_key'] as $column) {
                static::assertArrayHasKey($column, $discountColumns);
                static::assertSame($existingCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount'));
                static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount WHERE ' . $column . ' IS NOT NULL'));
            }

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['promotion_discount'];
                (new MigrationVariantRegistry())->resolve(Migration1568901713PromotionDiscount::class, 1568901713)->update($connection);
                static::assertSame($schema, $this->schema($connection)['promotion_discount']);
                static::assertSame($existingCount, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount'));
            }
        }
    }

    public function testProductVisibilityDuplicatesKeepNewestThenHighestId(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1568901713);

            $productId = Uuid::fromHexToBytes('1234567890abcdef1234567890abcdef');
            $productVersionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $connection->insert('product', [
                'id' => $productId,
                'version_id' => $productVersionId,
                'display_in_listing' => 1,
                'stock' => 1,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER]);

            $template = $connection->fetchAssociative('SELECT product.id AS product_id, product.version_id AS product_version_id, sales_channel.id AS sales_channel_id FROM product CROSS JOIN sales_channel LIMIT 1');
            static::assertIsArray($template);
            foreach (['product_id', 'product_version_id', 'sales_channel_id'] as $column) {
                if (\is_resource($template[$column])) {
                    $template[$column] = stream_get_contents($template[$column]);
                }
            }

            $duplicateIds = [
                Uuid::fromHexToBytes('00000000000000000000000000000011'),
                Uuid::fromHexToBytes('00000000000000000000000000000022'),
                Uuid::fromHexToBytes('00000000000000000000000000000033'),
            ];
            $rows = [
                [$duplicateIds[0], '2020-01-01 00:00:00.000'],
                [$duplicateIds[2], '2021-01-01 00:00:00.000'],
                [$duplicateIds[1], '2021-01-01 00:00:00.000'],
            ];
            foreach ($rows as [$id, $createdAt]) {
                $connection->insert('product_visibility', [
                    'id' => $id,
                    'product_id' => $template['product_id'],
                    'product_version_id' => $template['product_version_id'],
                    'sales_channel_id' => $template['sales_channel_id'],
                    'visibility' => 30,
                    'created_at' => $createdAt,
                ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::STRING]);
            }

            $initialCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility');
            static::assertSame([Migration1569403146ProductVisibilityUnique::class], $collection->migrateInPlace(1569403146));
            static::assertSame($initialCount - 2, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility WHERE id = ?', [$duplicateIds[2]], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility WHERE id IN (?, ?)', [$duplicateIds[0], $duplicateIds[1]], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['product_visibility'];
                (new MigrationVariantRegistry())->resolve(Migration1569403146ProductVisibilityUnique::class, 1569403146)->update($connection);
                static::assertSame($schema, $this->schema($connection)['product_visibility']);
                static::assertSame($initialCount - 2, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility'));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_visibility WHERE id = ?', [$duplicateIds[2]], [ParameterType::BINARY]));
            }
        }
    }

    public function testAppConfigCacheIdIsCreatedAndPreservedOnRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1569403146);
            $keyColumn = $connection === $this->postgres ? '"key"' : '`key`';

            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ? AND table_schema = ' . ($connection === $this->postgres ? 'current_schema()' : 'DATABASE()'), ['app_config']));
            static::assertSame([Migration1570187167AddedAppConfig::class], $collection->migrateInPlace(1570187167));
            $cacheId = $connection->fetchOne('SELECT value FROM app_config WHERE ' . $keyColumn . ' = ?', ['cache-id']);
            static::assertIsString($cacheId);
            static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $cacheId);
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_config WHERE ' . $keyColumn . ' = ?', ['cache-id']));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['app_config'];
                (new MigrationVariantRegistry())->resolve(Migration1570187167AddedAppConfig::class, 1570187167)->update($connection);
                static::assertSame($schema, $this->schema($connection)['app_config']);
                static::assertSame($cacheId, $connection->fetchOne('SELECT value FROM app_config WHERE ' . $keyColumn . ' = ?', ['cache-id']));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_config'));
            }
        }
    }

    public function testCmsSidebarLayoutSeedsSectionsSlotsAndRetriesWithoutOverwritingCustomizations(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1570187167);

            $defaultPageId = $connection->fetchOne('SELECT cms_page.id FROM cms_page_translation INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id WHERE cms_page.locked = 1 AND cms_page_translation.name = ?', ['Default category layout']);
            if (\is_resource($defaultPageId)) {
                $defaultPageId = stream_get_contents($defaultPageId);
            }
            static::assertIsString($defaultPageId);
            static::assertSame(1, (int) $connection->fetchOne('SELECT position FROM cms_block WHERE cms_page_id = ? AND name = ?', [$defaultPageId, 'Category listing'], [ParameterType::BINARY, ParameterType::STRING]));

            static::assertSame([Migration1570459127AddCmsSidebarLayout::class], $collection->migrateInPlace(1570459127));
            static::assertSame(2, (int) $connection->fetchOne('SELECT position FROM cms_block WHERE cms_page_id = ? AND name = ?', [$defaultPageId, 'Category listing'], [ParameterType::BINARY, ParameterType::STRING]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block WHERE cms_section_id IN (SELECT id FROM cms_section WHERE cms_page_id = ?) AND name = ?', [$defaultPageId, 'Filter'], [ParameterType::BINARY, ParameterType::STRING]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot INNER JOIN cms_block ON cms_block.id = cms_slot.cms_block_id INNER JOIN cms_section ON cms_section.id = cms_block.cms_section_id WHERE cms_block.name = ? AND cms_section.cms_page_id = ?', ['Filter', $defaultPageId], [ParameterType::STRING, ParameterType::BINARY]));

            $sidebarPageId = $connection->fetchOne('SELECT cms_page_id FROM cms_page_translation WHERE name = ?', ['Default category layout with sidebar']);
            if (\is_resource($sidebarPageId)) {
                $sidebarPageId = stream_get_contents($sidebarPageId);
            }
            static::assertIsString($sidebarPageId);
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section WHERE cms_page_id = ?', [$sidebarPageId], [ParameterType::BINARY]));
            static::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block INNER JOIN cms_section ON cms_section.id = cms_block.cms_section_id WHERE cms_section.cms_page_id = ?', [$sidebarPageId], [ParameterType::BINARY]));
            static::assertSame(6, (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot INNER JOIN cms_block ON cms_block.id = cms_slot.cms_block_id INNER JOIN cms_section ON cms_section.id = cms_block.cms_section_id WHERE cms_section.cms_page_id = ?', [$sidebarPageId], [ParameterType::BINARY]));
            static::assertSame(1 + (int) $connection->fetchOne('SELECT COUNT(*) FROM language INNER JOIN locale ON locale.id = language.translation_code_id WHERE locale.code = ? AND language.id <> ?', ['de-DE', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::STRING, ParameterType::BINARY]), (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_page_translation WHERE cms_page_id = ?', [$sidebarPageId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $connection->update('cms_page_translation', ['name' => 'Customized sidebar layout'], ['cms_page_id' => $sidebarPageId, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);
                $filterBlockId = $connection->fetchOne('SELECT cms_block.id FROM cms_block INNER JOIN cms_section ON cms_section.id = cms_block.cms_section_id WHERE cms_section.cms_page_id = ? AND cms_block.name = ?', [$defaultPageId, 'Filter'], [ParameterType::BINARY, ParameterType::STRING]);
                if (\is_resource($filterBlockId)) {
                    $filterBlockId = stream_get_contents($filterBlockId);
                }
                static::assertIsString($filterBlockId);
                $connection->update('cms_block', ['name' => 'Customized filter'], ['id' => $filterBlockId], [ParameterType::STRING, ParameterType::BINARY]);
                $counts = [
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_page'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_page_translation'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot_translation'),
                ];

                (new MigrationVariantRegistry())->resolve(Migration1570459127AddCmsSidebarLayout::class, 1570459127)->update($connection);

                static::assertSame($counts, [
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_page'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_page_translation'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_section'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_block'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot'),
                    (int) $connection->fetchOne('SELECT COUNT(*) FROM cms_slot_translation'),
                ]);
                static::assertSame('Customized sidebar layout', $connection->fetchOne('SELECT name FROM cms_page_translation WHERE cms_page_id = ? AND language_id = ?', [$sidebarPageId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame('Customized filter', $connection->fetchOne('SELECT name FROM cms_block WHERE id = ?', [$filterBlockId], [ParameterType::BINARY]));
                static::assertSame(1, (int) $connection->fetchOne('SELECT position FROM cms_block WHERE id = ?', [$filterBlockId], [ParameterType::BINARY]));
            }
        }
    }

    public function testDisplayGroupIsAddedBeforeOldListingFlagIsDestructivelyRemoved(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1566460168);
            $collection->migrateDestructiveInPlace(1566460168);

            $productId = Uuid::fromHexToBytes('0123456789abcdef0123456789abcdef');
            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $connection->insert('product', [
                'id' => $productId,
                'version_id' => $versionId,
                'display_in_listing' => 1,
                'stock' => 8,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER]);

            static::assertSame([Migration1566817701AddDisplayGroup::class], $collection->migrateInPlace(1566817701));
            static::assertSame(1, (int) $connection->fetchOne('SELECT display_in_listing FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT display_group FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            static::assertSame([Migration1566817701AddDisplayGroup::class], $collection->migrateDestructiveInPlace(1566817701));
            static::assertNotContains('display_in_listing', array_column($this->columns($connection)['product'], 'name'));
            static::assertContains('display_group', array_column($this->columns($connection)['product'], 'name'));
            static::assertNull($connection->fetchOne('SELECT display_group FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertSame(8, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['product'];
                $variant = (new MigrationVariantRegistry())->resolve(Migration1566817701AddDisplayGroup::class, 1566817701);
                $variant->update($connection);
                $variant->updateDestructive($connection);
                static::assertSame($schema, $this->schema($connection)['product']);
                static::assertNull($connection->fetchOne('SELECT display_group FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame(8, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            }

            static::assertSame([], $collection->migrateDestructiveInPlace(1566817701));
        }
    }

    public function testContactFormMailTemplateIsCreatedAndExistingTemplateIsUpdated(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1566817701);

            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = ?)', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]));
            static::assertSame([Migration1567431050ContactFormTemplate::class], $collection->migrateInPlace(1567431050));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = ?)', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]));

            $templateId = $connection->fetchOne('SELECT mail_template.id FROM mail_template INNER JOIN mail_template_type ON mail_template_type.id = mail_template.mail_template_type_id WHERE mail_template_type.technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]);
            if (\is_resource($templateId)) {
                $templateId = stream_get_contents($templateId);
            }
            static::assertIsString($templateId);
            static::assertSame(1, (int) $connection->fetchOne('SELECT system_default FROM mail_template WHERE id = ?', [$templateId], [ParameterType::BINARY]));

            $englishLanguageId = Uuid::fromHexToBytes('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
            $translation = $connection->fetchAssociative('SELECT subject, content_html, content_plain FROM mail_template_translation WHERE mail_template_id = ? AND language_id = ?', [$templateId, $englishLanguageId], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertIsArray($translation);
            static::assertSame('Contact form received - {{ salesChannel.name }}', $translation['subject']);
            static::assertStringContainsString('{{ contactFormData.firstName }}', $translation['content_html']);
            static::assertStringContainsString('{{ contactFormData.comment }}', $translation['content_plain']);
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation WHERE mail_template_id = ?', [$templateId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $germanLanguageId = $connection->fetchOne('SELECT language.id FROM language INNER JOIN locale ON locale.id = language.locale_id WHERE locale.code = ?', ['de-DE']);
                if (\is_resource($germanLanguageId)) {
                    $germanLanguageId = stream_get_contents($germanLanguageId);
                }
                $connection->update('mail_template_translation', ['subject' => 'Customized contact subject'], ['mail_template_id' => $templateId, 'language_id' => $englishLanguageId], [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);
                $connection->insert('mail_template_translation', [
                    'mail_template_id' => $templateId,
                    'language_id' => $germanLanguageId,
                    'subject' => 'Benutzerdefinierter Betreff',
                    'created_at' => '2020-01-01 00:00:00.123',
                ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);

                (new MigrationVariantRegistry())->resolve(Migration1567431050ContactFormTemplate::class, 1567431050)->update($connection);
                $existingTemplateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = ?)', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]);
                if (\is_resource($existingTemplateId)) {
                    $existingTemplateId = stream_get_contents($existingTemplateId);
                }
                static::assertSame($templateId, $existingTemplateId);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation WHERE mail_template_id = ?', [$templateId], [ParameterType::BINARY]));
                static::assertSame('Contact form received - {{ salesChannel.name }}', $connection->fetchOne('SELECT subject FROM mail_template_translation WHERE mail_template_id = ? AND language_id = ?', [$templateId, $englishLanguageId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertGreaterThan(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE id = ? AND updated_at IS NOT NULL', [$templateId], [ParameterType::BINARY]));
            }
        }
    }

    public function testPaymentDescriptionsAreUpdatedOnlyForExactOldTextsAndRetryPreservesCustomText(): void
    {
        $expectedDescriptions = [
            'Invoice' => 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first. This is to avoid defaults on payment.',
            'Rechnung' => 'Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnungen automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.',
            'Direct Debit' => 'Pre-authorized payment, funds are withdrawn directly from the debited account.',
            'Lastschrift' => 'Vorab autorisierte Zahlungsvereinbarung, Zahlungen werden direkt vom zu belastenden Konto abgebucht.',
            'Cash on delivery' => 'Payment upon receipt of goods.',
            'Nachnahme' => 'Zahlung bei Erhalt der Ware.',
        ];

        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1566460168);
            $collection->migrateDestructiveInPlace(1566293076);

            static::assertSame([Migration1566460168UpdateTexts::class], $collection->migrateDestructiveInPlace(1566460168));
            foreach ($expectedDescriptions as $name => $description) {
                static::assertSame($description, $connection->fetchOne('SELECT description FROM payment_method_translation WHERE name = ?', [$name]));
            }

            if ($connection === $this->postgres) {
                $connection->update('payment_method_translation', ['description' => 'Custom payment description'], ['name' => 'Cash on delivery'], [ParameterType::STRING, ParameterType::STRING]);
                (new MigrationVariantRegistry())->resolve(Migration1566460168UpdateTexts::class, 1566460168)->updateDestructive($connection);
                static::assertSame('Custom payment description', $connection->fetchOne('SELECT description FROM payment_method_translation WHERE name = ?', ['Cash on delivery']));
                foreach (array_diff_key($expectedDescriptions, ['Cash on delivery' => true]) as $name => $description) {
                    static::assertSame($description, $connection->fetchOne('SELECT description FROM payment_method_translation WHERE name = ?', [$name]));
                }
            }
        }
    }

    public function testPromotionSetGroupsDefaultOffAndRetryPreservesCustomValue(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565270366);

            $promotionId = Uuid::fromHexToBytes('0123456789abcdef0123456789abcdef');
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('promotion', ['id' => $promotionId, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING]);

            static::assertSame([Migration1565346846Promotion::class], $collection->migrateInPlace(1565346846));
            static::assertSame(0, (int) $connection->fetchOne('SELECT use_setgroups FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            $connection->update('promotion', ['use_setgroups' => 1], ['id' => $promotionId], [ParameterType::INTEGER, ParameterType::BINARY]);

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['promotion'];
                (new MigrationVariantRegistry())->resolve(Migration1565346846Promotion::class, 1565346846)->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT use_setgroups FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['promotion']);
            }
        }
    }

    public function testProductExportSchemaAndComparisonChannelSeedsAreRetrySafe(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565346846);

            $createdAt = '2020-01-01 00:00:00.123';
            $productStreamId = Uuid::fromHexToBytes('1234567890abcdef1234567890abcdef');
            $exportId = Uuid::fromHexToBytes('abcdef1234567890abcdef1234567890');
            $salesChannelId = $connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');
            if (\is_resource($salesChannelId)) {
                $salesChannelId = stream_get_contents($salesChannelId);
            }
            $connection->insert('product_stream', ['id' => $productStreamId, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING]);

            static::assertSame([Migration1565705280ProductExport::class], $collection->migrateInPlace(1565705280));
            $typeId = Uuid::fromHexToBytes('ed535e5722134ac1aa6524f73e26881b');
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_channel_type WHERE id = ?', [$typeId], [ParameterType::BINARY]));
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_channel_type_translation WHERE sales_channel_type_id = ?', [$typeId], [ParameterType::BINARY]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_channel_type_translation WHERE sales_channel_type_id = ? AND name = ?', [$typeId, 'Product comparison'], [ParameterType::BINARY, ParameterType::STRING]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_channel_type_translation WHERE sales_channel_type_id = ? AND name = ?', [$typeId, 'Produktvergleich'], [ParameterType::BINARY, ParameterType::STRING]));

            $productExportTable = $connection->quoteSingleIdentifier('product_export');
            $intervalColumn = $connection->quoteSingleIdentifier('interval');
            $connection->executeStatement(
                'INSERT INTO ' . $productExportTable . ' (id, product_stream_id, sales_channel_id, file_name, access_key, encoding, file_format, ' . $intervalColumn . ', created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$exportId, $productStreamId, $salesChannelId, 'product-export-contract.xml', 'contract-access-key', 'UTF-8', 'xml', 60, $createdAt],
                [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING],
            );
            static::assertSame(60, (int) $connection->fetchOne('SELECT ' . $intervalColumn . ' FROM product_export WHERE id = ?', [$exportId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT include_variants FROM product_export WHERE id = ?', [$exportId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT generate_by_cronjob FROM product_export WHERE id = ?', [$exportId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $languageEnglish = $connection->fetchOne('SELECT language.id FROM language INNER JOIN locale ON locale.id = language.locale_id WHERE locale.code = ?', ['en-GB']);
                if (\is_resource($languageEnglish)) {
                    $languageEnglish = stream_get_contents($languageEnglish);
                }
                $connection->update('sales_channel_type', ['icon_name' => 'custom-icon'], ['id' => $typeId], [ParameterType::STRING, ParameterType::BINARY]);
                $connection->update('sales_channel_type_translation', ['name' => 'Customized comparison'], ['sales_channel_type_id' => $typeId, 'language_id' => $languageEnglish], [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);
                $schema = $this->schema($connection);
                (new MigrationVariantRegistry())->resolve(Migration1565705280ProductExport::class, 1565705280)->update($connection);
                static::assertSame('custom-icon', $connection->fetchOne('SELECT icon_name FROM sales_channel_type WHERE id = ?', [$typeId], [ParameterType::BINARY]));
                static::assertSame('Customized comparison', $connection->fetchOne('SELECT name FROM sales_channel_type_translation WHERE sales_channel_type_id = ? AND language_id = ?', [$typeId, $languageEnglish], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_channel_type_translation WHERE sales_channel_type_id = ?', [$typeId], [ParameterType::BINARY]));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_export WHERE id = ?', [$exportId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection));
            }
        }
    }

    public function testAutoIncrementColumnsAreRecreatedDestructivelyAndGenerateUniqueValues(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1566293076);
            $collection->migrateDestructiveInPlace(1565705280);

            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $productIds = [Uuid::fromHexToBytes('11223344556677889900aabbccddeeff'), Uuid::fromHexToBytes('ffeeddccbbaa00998877665544332211')];
            $categoryIds = [Uuid::fromHexToBytes('00112233445566778899aabbccddeeff'), Uuid::fromHexToBytes('ffeeddccbbaa99887766554433221100')];
            $createdAt = '2020-01-01 00:00:00.123';
            foreach ($productIds as $productId) {
                $connection->insert('product', ['id' => $productId, 'version_id' => $versionId, 'stock' => 5], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER]);
            }
            foreach ($categoryIds as $categoryId) {
                $connection->insert('category', ['id' => $categoryId, 'version_id' => $versionId, 'type' => 'page', 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
            }

            static::assertSame([Migration1566293076AddAutoIncrement::class], $collection->migrateDestructiveInPlace(1566293076));
            $generatedValues = [];
            foreach (['product' => $productIds, 'category' => $categoryIds] as $table => $ids) {
                $autoIncrementColumn = array_values(array_filter($this->columns($connection)[$table], static fn (array $column): bool => $column['name'] === 'auto_increment'))[0];
                static::assertSame(1, $autoIncrementColumn['identity_column']);
                $values = [];
                foreach ($ids as $id) {
                    $value = (int) $connection->fetchOne('SELECT auto_increment FROM ' . $connection->quoteSingleIdentifier($table) . ' WHERE id = ? AND version_id = ?', [$id, $versionId], [ParameterType::BINARY, ParameterType::BINARY]);
                    static::assertGreaterThan(0, $value);
                    $values[] = $value;
                }
                static::assertCount(2, array_unique($values));
                $generatedValues[$table] = $values;
            }

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection);
                (new MigrationVariantRegistry())->resolve(Migration1566293076AddAutoIncrement::class, 1566293076)->updateDestructive($connection);
                static::assertSame($schema, $this->schema($connection));
                foreach (['product' => $productIds, 'category' => $categoryIds] as $table => $ids) {
                    foreach ($ids as $index => $id) {
                        static::assertSame($generatedValues[$table][$index], (int) $connection->fetchOne('SELECT auto_increment FROM ' . $connection->quoteSingleIdentifier($table) . ' WHERE id = ? AND version_id = ?', [$id, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                    }
                }
            }

            static::assertSame([], $collection->migrateDestructiveInPlace(1566293076));
        }

        static::assertSame($this->schema($this->mysql), $this->schema($this->postgres));
    }

    public function testPromotionSetGroupRuleCascadeDeletesBothReferencesAndRetryPreservesLinks(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565270155);

            $setGroupId = Uuid::fromHexToBytes('abcdef1234567890abcdef1234567890');
            $firstRuleId = Uuid::fromHexToBytes('1234567890abcdef1234567890abcdef');
            $secondRuleId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $promotionId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('promotion', ['id' => $promotionId, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING]);
            foreach ([$firstRuleId, $secondRuleId] as $ruleId) {
                $connection->insert('rule', ['id' => $ruleId, 'name' => 'Set group rule', 'priority' => 1, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING]);
            }
            $connection->insert('promotion_setgroup', [
                'id' => $setGroupId,
                'promotion_id' => $promotionId,
                'packager_key' => 'price-range',
                'sorter_key' => 'sort-by-price',
                'value' => 1.75,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

            static::assertSame([Migration1565270366PromotionSetGroupRule::class], $collection->migrateInPlace(1565270366));
            foreach ([$firstRuleId, $secondRuleId] as $ruleId) {
                $connection->insert('promotion_setgroup_rule', ['setgroup_id' => $setGroupId, 'rule_id' => $ruleId], [ParameterType::BINARY, ParameterType::BINARY]);
            }
            static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup_rule WHERE setgroup_id = ?', [$setGroupId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['promotion_setgroup_rule'];
                (new MigrationVariantRegistry())->resolve(Migration1565270366PromotionSetGroupRule::class, 1565270366)->update($connection);
                static::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup_rule WHERE setgroup_id = ?', [$setGroupId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['promotion_setgroup_rule']);
            }

            $connection->delete('rule', ['id' => $firstRuleId], [ParameterType::BINARY]);
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup_rule WHERE setgroup_id = ?', [$setGroupId], [ParameterType::BINARY]));
            $connection->delete('promotion_setgroup', ['id' => $setGroupId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup_rule WHERE setgroup_id = ?', [$setGroupId], [ParameterType::BINARY]));
        }
    }

    public function testPromotionSetGroupRetainsValuesAndCascadesOnPromotionDelete(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565079228);

            $promotionId = Uuid::fromHexToBytes('1234567890abcdef1234567890abcdef');
            $setGroupId = Uuid::fromHexToBytes('abcdef1234567890abcdef1234567890');
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('promotion', ['id' => $promotionId, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING]);

            static::assertSame([Migration1565270155PromotionSetGroup::class], $collection->migrateInPlace(1565270155));
            $connection->insert('promotion_setgroup', [
                'id' => $setGroupId,
                'promotion_id' => $promotionId,
                'packager_key' => 'price-range',
                'sorter_key' => 'sort-by-price',
                'value' => 1.75,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
            static::assertSame(1.75, (float) $connection->fetchOne('SELECT value FROM promotion_setgroup WHERE id = ?', [$setGroupId], [ParameterType::BINARY]));

            $schema = $this->schema($connection)['promotion_setgroup'];
            if ($connection === $this->postgres) {
                (new MigrationVariantRegistry())->resolve(Migration1565270155PromotionSetGroup::class, 1565270155)->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup WHERE id = ? AND promotion_id = ?', [$setGroupId, $promotionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['promotion_setgroup']);
            }

            $connection->delete('promotion', ['id' => $promotionId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_setgroup WHERE id = ?', [$setGroupId], [ParameterType::BINARY]));
        }
    }

    public function testAclTablesAdminDefaultsAndCascadeDeleteAreAppliedAndRetryIsSafe(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565007156);

            $userId = Uuid::fromHexToBytes('0123456789abcdef0123456789abcdef');
            $roleId = Uuid::fromHexToBytes('abcdef0123456789abcdef0123456789');
            $createdAt = '2020-01-01 00:00:00.123';
            $userTable = $connection->quoteSingleIdentifier('user');
            $connection->executeStatement(
                'INSERT INTO ' . $userTable . ' (id, username, password, first_name, last_name, email, active, locale_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT id FROM locale WHERE code = \'en-GB\' LIMIT 1), ?)',
                [$userId, 'acl-structure-user', 'password-hash', 'ACL', 'User', 'acl-structure-user@example.com', 1, $createdAt],
                [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING],
            );

            static::assertSame([Migration1565079228AddAclStructure::class], $collection->migrateInPlace(1565079228));
            static::assertSame(1, (int) $connection->fetchOne('SELECT admin FROM ' . $userTable . ' WHERE id = ?', [$userId], [ParameterType::BINARY]));

            $connection->insert('acl_role', ['id' => $roleId, 'name' => 'Contract role', 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);
            $connection->insert('acl_resource', ['resource' => 'product', 'privilege' => 'read', 'acl_role_id' => $roleId, 'created_at' => $createdAt], [ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING]);
            $connection->insert('acl_user_role', ['user_id' => $userId, 'acl_role_id' => $roleId, 'created_at' => $createdAt], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING]);
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM acl_resource WHERE acl_role_id = ?', [$roleId], [ParameterType::BINARY]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM acl_user_role WHERE user_id = ? AND acl_role_id = ?', [$userId, $roleId], [ParameterType::BINARY, ParameterType::BINARY]));

            $schema = $this->schema($connection);
            $connection->delete('acl_role', ['id' => $roleId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM acl_resource WHERE acl_role_id = ?', [$roleId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM acl_user_role WHERE user_id = ? AND acl_role_id = ?', [$userId, $roleId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $connection->update($userTable, ['admin' => 0], ['id' => $userId], [ParameterType::INTEGER, ParameterType::BINARY]);
                (new MigrationVariantRegistry())->resolve(Migration1565079228AddAclStructure::class, 1565079228)->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT admin FROM ' . $userTable . ' WHERE id = ?', [$userId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection));
            }
        }
    }

    public function testAutoIncrementColumnsAreRemovedOnlyInDestructivePass(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1565007156);
            $collection->migrateDestructiveInPlace(1564475053);

            $productId = str_repeat('P', 16);
            $categoryId = str_repeat('C', 16);
            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('product', [
                'id' => $productId,
                'version_id' => $versionId,
                'stock' => 17,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER]);
            $connection->insert('category', [
                'id' => $categoryId,
                'version_id' => $versionId,
                'type' => 'page',
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING]);

            static::assertGreaterThan(0, (int) $connection->fetchOne('SELECT auto_increment FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertGreaterThan(0, (int) $connection->fetchOne('SELECT auto_increment FROM category WHERE id = ? AND version_id = ?', [$categoryId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertContains('auto_increment', array_column($this->columns($connection)['product'], 'name'));
            static::assertContains('auto_increment', array_column($this->columns($connection)['category'], 'name'));

            static::assertSame([Migration1565007156RemoveAutoIncrement::class], $collection->migrateDestructiveInPlace(1565007156));
            static::assertNotContains('auto_increment', array_column($this->columns($connection)['product'], 'name'));
            static::assertNotContains('auto_increment', array_column($this->columns($connection)['category'], 'name'));
            foreach (['product', 'category'] as $table) {
                foreach ($this->indexes($connection)[$table] ?? [] as [, $indexedColumns]) {
                    static::assertNotContains('auto_increment', $indexedColumns);
                }
            }
            static::assertSame(17, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertSame('page', $connection->fetchOne('SELECT type FROM category WHERE id = ? AND version_id = ?', [$categoryId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                static::assertNull($connection->fetchOne('SELECT to_regclass(\'product_auto_increment_seq\')'));
                static::assertNull($connection->fetchOne('SELECT to_regclass(\'category_auto_increment_seq\')'));
                (new MigrationVariantRegistry())->resolve(Migration1565007156RemoveAutoIncrement::class, 1565007156)->updateDestructive($connection);
                static::assertNotContains('auto_increment', array_column($this->columns($connection)['product'], 'name'));
                static::assertNotContains('auto_increment', array_column($this->columns($connection)['category'], 'name'));
                static::assertSame(17, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame('page', $connection->fetchOne('SELECT type FROM category WHERE id = ? AND version_id = ?', [$categoryId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            }

            static::assertSame([], $collection->migrateDestructiveInPlace(1565007156));
        }
    }

    public function testSaveDocumentsConfigIsRemovedAndRetryIsSafe(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1563949275);

            static::assertGreaterThan(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ?', ['core.saveDocuments']));
            static::assertSame([Migration1564475053RemoveSaveDocumentsConfig::class], $collection->migrateInPlace(1564475053));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ?', ['core.saveDocuments']));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ?', ['core.store.apiUri']));

            (new MigrationVariantRegistry())->resolve(Migration1564475053RemoveSaveDocumentsConfig::class, 1564475053)->update($connection);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ?', ['core.saveDocuments']));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ?', ['core.store.apiUri']));
        }
    }

    public function testOrderCustomerCompanyIsNullableAndRetryPreservesCustomValue(): void
    {
        $connection = $this->postgres;
        $collection = $this->collection($connection);
        $collection->sync();
        $collection->migrateInPlace(1563805586);

        $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
        $orderId = Uuid::fromHexToBytes('1234567890abcdef1234567890abcdef');
        $orderCustomerId = Uuid::fromHexToBytes('abcdef1234567890abcdef1234567890');
        $createdAt = '2020-01-01 00:00:00.123';
        $orderTable = $connection->quoteSingleIdentifier('order');
        $connection->executeStatement(
            'INSERT INTO ' . $orderTable . ' (id, version_id, state_id, currency_id, sales_channel_id, language_id, billing_address_id, billing_address_version_id, price, order_date_time, shipping_costs, created_at) VALUES (?, ?, ?, (SELECT id FROM currency LIMIT 1), (SELECT id FROM sales_channel LIMIT 1), (SELECT language_id FROM sales_channel_language LIMIT 1), ?, ?, ?, ?, ?, ?)',
            [
                $orderId,
                $versionId,
                Uuid::fromHexToBytes('33333333333333333333333333333333'),
                Uuid::fromHexToBytes('44444444444444444444444444444444'),
                $versionId,
                '{"totalPrice":1,"netPrice":1,"positionPrice":1}',
                '2020-01-02 03:04:05.123',
                '{"totalPrice":0}',
                $createdAt,
            ],
            [
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ],
        );
        $connection->insert('order_customer', [
            'id' => $orderCustomerId,
            'version_id' => $versionId,
            'customer_id' => null,
            'order_id' => $orderId,
            'order_version_id' => $versionId,
            'email' => 'company-contract@example.com',
            'salutation_id' => $connection->fetchOne('SELECT id FROM salutation LIMIT 1'),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'created_at' => $createdAt,
        ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::NULL, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

        static::assertSame([Migration1563949275AddCompanyToOrderCustomer::class], $collection->migrateInPlace(1563949275));
        static::assertNull($connection->fetchOne('SELECT company FROM order_customer WHERE id = ? AND version_id = ?', [$orderCustomerId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
        $connection->update('order_customer', ['company' => 'Acme Corporation'], ['id' => $orderCustomerId, 'version_id' => $versionId], [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY]);

        $schema = $this->schema($connection)['order_customer'];
        (new MigrationVariantRegistry())->resolve(Migration1563949275AddCompanyToOrderCustomer::class, 1563949275)->update($connection);

        static::assertSame('Acme Corporation', $connection->fetchOne('SELECT company FROM order_customer WHERE id = ? AND version_id = ?', [$orderCustomerId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
        static::assertSame($schema, $this->schema($connection)['order_customer']);
    }

    public function testOrderLanguageUsesCustomerThenSalesChannelAndRetryPreservesValues(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1563518181);

            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $customerOrderId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $guestOrderId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $customerId = Uuid::fromHexToBytes('abcdef0123456789abcdef0123456789');
            $createdAt = '2020-01-01 00:00:00.123';
            $customerLanguageId = $connection->fetchOne(<<<'SQL'
                SELECT language.id
                FROM language
                INNER JOIN locale ON locale.id = language.locale_id
                WHERE locale.code = 'de-DE'
                SQL);
            if (\is_resource($customerLanguageId)) {
                $customerLanguageId = stream_get_contents($customerLanguageId);
            }
            static::assertIsString($customerLanguageId);

            $connection->executeStatement(<<<'SQL'
                INSERT INTO customer (
                    id, customer_group_id, default_payment_method_id, sales_channel_id, language_id,
                    default_billing_address_id, default_shipping_address_id, customer_number, salutation_id,
                    first_name, last_name, email, created_at
                ) VALUES (
                    ?, (SELECT id FROM customer_group LIMIT 1), (SELECT id FROM payment_method LIMIT 1),
                    (SELECT id FROM sales_channel LIMIT 1), ?, ?, ?, ?,
                    (SELECT id FROM salutation LIMIT 1), ?, ?, ?, ?
                )
                SQL, [
                $customerId,
                $customerLanguageId,
                Uuid::fromHexToBytes('11111111111111111111111111111111'),
                Uuid::fromHexToBytes('22222222222222222222222222222222'),
                'customer-language-contract',
                'Test',
                'Customer',
                'customer-language-contract@example.com',
                $createdAt,
            ], [
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ]);

            $orderTable = $connection->quoteSingleIdentifier('order');
            foreach ([$customerOrderId, $guestOrderId] as $orderId) {
                $connection->executeStatement(
                    'INSERT INTO ' . $orderTable . ' (id, version_id, state_id, currency_id, sales_channel_id, billing_address_id, billing_address_version_id, price, order_date_time, shipping_costs, created_at) VALUES (?, ?, ?, (SELECT id FROM currency LIMIT 1), (SELECT id FROM sales_channel LIMIT 1), ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        $versionId,
                        Uuid::fromHexToBytes('33333333333333333333333333333333'),
                        Uuid::fromHexToBytes('44444444444444444444444444444444'),
                        $versionId,
                        '{"totalPrice":1,"netPrice":1,"positionPrice":1}',
                        '2020-01-02 03:04:05.123',
                        '{"totalPrice":0}',
                        $createdAt,
                    ],
                    [
                        ParameterType::BINARY,
                        ParameterType::BINARY,
                        ParameterType::BINARY,
                        ParameterType::BINARY,
                        ParameterType::BINARY,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                        ParameterType::STRING,
                    ],
                );
            }

            $connection->insert('order_customer', [
                'id' => Uuid::fromHexToBytes('55555555555555555555555555555555'),
                'version_id' => $versionId,
                'customer_id' => $customerId,
                'order_id' => $customerOrderId,
                'order_version_id' => $versionId,
                'email' => 'customer-language-contract@example.com',
                'salutation_id' => $connection->fetchOne('SELECT id FROM salutation LIMIT 1'),
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);
            $connection->insert('order_customer', [
                'id' => Uuid::fromHexToBytes('66666666666666666666666666666666'),
                'version_id' => $versionId,
                'customer_id' => null,
                'order_id' => $guestOrderId,
                'order_version_id' => $versionId,
                'email' => 'guest-language-contract@example.com',
                'salutation_id' => $connection->fetchOne('SELECT id FROM salutation LIMIT 1'),
                'first_name' => 'Guest',
                'last_name' => 'Customer',
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::NULL, ParameterType::BINARY, ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]);

            static::assertSame([Migration1563805586AddLanguageToOrder::class], $collection->migrateInPlace(1563805586));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $orderTable . ' WHERE id = ? AND version_id = ? AND language_id = ?', [$customerOrderId, $versionId, $customerLanguageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
            $guestLanguageQuery = 'SELECT COUNT(*) FROM ' . $orderTable . ' WHERE id = ? AND version_id = ? AND language_id = (SELECT language.id FROM language INNER JOIN sales_channel_language ON language.id = sales_channel_language.language_id WHERE sales_channel_language.sales_channel_id = ' . $orderTable . '.sales_channel_id LIMIT 1)';
            static::assertSame(1, (int) $connection->fetchOne($guestLanguageQuery, [$guestOrderId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1563805586AddLanguageToOrder::class, 1563805586);
                $schema = $this->schema($connection)['order'];
                $variant->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM ' . $orderTable . ' WHERE id = ? AND version_id = ? AND language_id = ?', [$customerOrderId, $versionId, $customerLanguageId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['order']);
            }
        }
    }

    public function testPromotionDiscountMaxValueIsNullableAndRetryPreservesCustomValue(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1563288227);

            $promotionId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $discountId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('promotion', ['id' => $promotionId, 'created_at' => $createdAt], [ParameterType::BINARY]);
            $connection->insert('promotion_discount', [
                'id' => $discountId,
                'promotion_id' => $promotionId,
                'scope' => 'cart',
                'type' => 'percentage',
                'value' => 10,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);

            static::assertSame([Migration1563518181PromotionDiscount::class], $collection->migrateInPlace(1563518181));
            static::assertNull($connection->fetchOne('SELECT max_value FROM promotion_discount WHERE id = ?', [$discountId], [ParameterType::BINARY]));
            static::assertSame(10.0, (float) $connection->fetchOne('SELECT value FROM promotion_discount WHERE id = ?', [$discountId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1563518181PromotionDiscount::class, 1563518181);
                $schema = $this->schema($connection)['promotion_discount'];
                $connection->update('promotion_discount', ['max_value' => 23.5], ['id' => $discountId], [ParameterType::STRING, ParameterType::BINARY]);
                $variant->update($connection);
                static::assertSame(23.5, (float) $connection->fetchOne('SELECT max_value FROM promotion_discount WHERE id = ?', [$discountId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['promotion_discount']);
            }
        }
    }

    public function testMarkAsNewConfigIsSeededOnceAndRetryPreservesCustomValue(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1563180880);

            static::assertSame([Migration1563288227MarkAsNewConfig::class], $collection->migrateInPlace(1563288227));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['core.listing.markAsNew']));
            $configuration = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['core.listing.markAsNew']);
            static::assertIsString($configuration);
            static::assertSame('30', json_decode($configuration, true, flags: \JSON_THROW_ON_ERROR)['_value']);

            if ($connection === $this->postgres) {
                $connection->update('system_config', ['configuration_value' => '{"_value":"custom"}'], ['configuration_key' => 'core.listing.markAsNew']);
                $variant = (new MigrationVariantRegistry())->resolve(Migration1563288227MarkAsNewConfig::class, 1563288227);
                $variant->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['core.listing.markAsNew']));
                static::assertSame('custom', $connection->fetchOne('SELECT configuration_value ->> \'_value\' FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['core.listing.markAsNew']));
            }
        }
    }

    public function testDefaultThumbnailSizesPreserveExistingSizesAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562933907);

            $existingId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $connection->insert('media_thumbnail_size', [
                'id' => $existingId,
                'width' => 400,
                'height' => 400,
                'custom_fields' => '{"custom":"value"}',
                'created_at' => '2020-01-01 00:00:00.000',
            ], [ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING]);

            static::assertSame([Migration1563180880AddDefaultThumbnailSizes::class], $collection->migrateInPlace(1563180880));
            static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM media_thumbnail_size WHERE (width, height) IN ((400, 400), (800, 800), (1920, 1920))'));
            static::assertSame(['custom' => 'value'], json_decode($connection->fetchOne('SELECT custom_fields FROM media_thumbnail_size WHERE id = ?', [$existingId], [ParameterType::BINARY]), true, flags: \JSON_THROW_ON_ERROR));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM media_folder_configuration_media_thumbnail_size WHERE media_thumbnail_size_id = ?', [$existingId], [ParameterType::BINARY]));
            static::assertSame(6, (int) $connection->fetchOne(<<<'SQL'
                SELECT COUNT(*)
                FROM media_folder_configuration_media_thumbnail_size AS association
                INNER JOIN media_folder ON media_folder.media_folder_configuration_id = association.media_folder_configuration_id
                WHERE media_folder.name IN ('Product Media', 'Category Media', 'Cms Page Media')
                SQL));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1563180880AddDefaultThumbnailSizes::class, 1563180880);
                $variant->update($connection);
                static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM media_thumbnail_size WHERE (width, height) IN ((400, 400), (800, 800), (1920, 1920))'));
                static::assertSame(6, (int) $connection->fetchOne(<<<'SQL'
                    SELECT COUNT(*)
                    FROM media_folder_configuration_media_thumbnail_size AS association
                    INNER JOIN media_folder ON media_folder.media_folder_configuration_id = association.media_folder_configuration_id
                    WHERE media_folder.name IN ('Product Media', 'Category Media', 'Cms Page Media')
                    SQL));
                static::assertSame(['custom' => 'value'], json_decode($connection->fetchOne('SELECT custom_fields FROM media_thumbnail_size WHERE id = ?', [$existingId], [ParameterType::BINARY]), true, flags: \JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testContactFormMailTypeAndEventActionAreSeededOnce(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562841035);

            static::assertSame([Migration1562933907ContactForm::class], $collection->migrateInPlace(1562933907));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]));
            $typeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]);
            if (\is_resource($typeId)) {
                $typeId = stream_get_contents($typeId);
            }
            static::assertIsString($typeId);
            $typeIdHex = bin2hex($typeId);
            $availableEntities = $connection->fetchOne('SELECT available_entities FROM mail_template_type WHERE id = ?', [$typeId], [ParameterType::BINARY]);
            static::assertSame(['salesChannel' => 'sales_channel'], json_decode($availableEntities, true, flags: \JSON_THROW_ON_ERROR));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type_translation WHERE mail_template_type_id = ? AND name = ?', [$typeId, 'Contact form'], [ParameterType::BINARY, ParameterType::STRING]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type_translation WHERE mail_template_type_id = ? AND name = ?', [$typeId, 'Kontaktformular'], [ParameterType::BINARY, ParameterType::STRING]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = ? AND action_name = ?', [ContactFormEvent::EVENT_NAME, SendMailAction::ACTION_NAME]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1562933907ContactForm::class, 1562933907);
                $variant->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]));
                static::assertSame($typeIdHex, $connection->fetchOne('SELECT encode(id, \'hex\') FROM mail_template_type WHERE technical_name = ?', [MailTemplateTypes::MAILTYPE_CONTACT_FORM]));
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = ? AND action_name = ?', [ContactFormEvent::EVENT_NAME, SendMailAction::ACTION_NAME]));
            }
        }
    }

    public function testProductChildCountIsAddedAsNullableAndRetryPreservesIt(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562684474);

            $productId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $connection->insert('product', ['id' => $productId, 'version_id' => $versionId, 'stock' => 4], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER]);

            static::assertSame([Migration1562841035AddProductChildCount::class], $collection->migrateInPlace(1562841035));
            static::assertNull($connection->fetchOne('SELECT child_count FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1562841035AddProductChildCount::class, 1562841035);
                $schema = $this->schema($connection)['product'];
                $connection->update('product', ['child_count' => 3], ['id' => $productId, 'version_id' => $versionId], [ParameterType::INTEGER, ParameterType::BINARY, ParameterType::BINARY]);
                $variant->update($connection);
                static::assertSame(3, (int) $connection->fetchOne('SELECT child_count FROM product WHERE id = ? AND version_id = ?', [$productId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['product']);
            }
        }
    }

    public function testDeliveryTimeMigrationPreservesDefaultsAndDoesNotOverwriteRetryValues(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562579120);

            $rootId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $childId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            $connection->insert('product', [
                'id' => $rootId,
                'version_id' => $versionId,
                'stock' => 12,
                'min_delivery_time' => 2,
                'max_delivery_time' => 5,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER]);
            $connection->insert('product', [
                'id' => $childId,
                'version_id' => $versionId,
                'parent_id' => $rootId,
                'parent_version_id' => $versionId,
                'stock' => 7,
                'min_delivery_time' => 1,
                'max_delivery_time' => 4,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER]);

            static::assertSame([Migration1562684474AddDeliveryTime::class], $collection->migrateInPlace(1562684474));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM product WHERE id = ? AND version_id = ? AND delivery_time_id = (SELECT id FROM delivery_time LIMIT 1)', [$rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT delivery_time_id FROM product WHERE id = ? AND version_id = ?', [$childId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertSame(12, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertSame(7, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$childId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            $deliveryTimeColumn = $connection->quoteSingleIdentifier('deliveryTime');
            static::assertNull($connection->fetchOne('SELECT ' . $deliveryTimeColumn . ' FROM product WHERE id = ? AND version_id = ?', [$rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $customDeliveryTimeId = Uuid::fromHexToBytes('abcdef0123456789abcdef0123456789');
                $connection->executeStatement('UPDATE product SET delivery_time_id = ?, "deliveryTime" = ? WHERE id = ? AND version_id = ?', [$customDeliveryTimeId, $customDeliveryTimeId, $rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]);
                $variant = (new MigrationVariantRegistry())->resolve(Migration1562684474AddDeliveryTime::class, 1562684474);
                $schema = $this->schema($connection)['product'];
                $variant->update($connection);
                static::assertSame($customDeliveryTimeId, stream_get_contents($connection->fetchOne('SELECT delivery_time_id FROM product WHERE id = ? AND version_id = ?', [$rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY])));
                static::assertSame(bin2hex($customDeliveryTimeId), $connection->fetchOne('SELECT encode("deliveryTime", \'hex\') FROM product WHERE id = ? AND version_id = ?', [$rootId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['product']);
            }
        }
    }

    public function testProductAvailabilityFieldsPreserveStockAndRetryPreservesCustomValues(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562324772);

            $nullStockId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $stockId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $versionId = Uuid::fromHexToBytes('00000000000000000000000000000001');
            foreach ([[$nullStockId, null, 'product-null-stock'], [$stockId, 17, 'product-stock']] as [$id, $stock, $ean]) {
                $connection->executeStatement(
                    'INSERT INTO product (id, version_id, stock, ean) VALUES (?, ?, ?, ?)',
                    [$id, $versionId, $stock, $ean],
                    [ParameterType::BINARY, ParameterType::BINARY, $stock === null ? ParameterType::NULL : ParameterType::INTEGER, ParameterType::STRING],
                );
            }

            try {
                $collection->migrateInPlace(1562579120);
                static::fail('The migration must reject a product with NULL stock when making stock non-nullable.');
            } catch (NotNullConstraintViolationException) {
                static::assertNull($connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$nullStockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            }

            $connection->delete('product', ['id' => $nullStockId, 'version_id' => $versionId], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame([Migration1562579120ProductAvailableFields::class], $collection->migrateInPlace(1562579120));
            static::assertSame(17, (int) $connection->fetchOne('SELECT stock FROM product WHERE id = ? AND version_id = ?', [$stockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT available FROM product WHERE id = ? AND version_id = ?', [$stockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT available_stock FROM product WHERE id = ? AND version_id = ?', [$stockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $connection->update('product', ['available_stock' => 9, 'available' => 0], ['id' => $stockId, 'version_id' => $versionId], [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::BINARY, ParameterType::BINARY]);
                $variant = (new MigrationVariantRegistry())->resolve(Migration1562579120ProductAvailableFields::class, 1562579120);
                $schema = $this->schema($connection)['product'];
                $variant->update($connection);
                static::assertSame(9, (int) $connection->fetchOne('SELECT available_stock FROM product WHERE id = ? AND version_id = ?', [$stockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame(0, (int) $connection->fetchOne('SELECT available FROM product WHERE id = ? AND version_id = ?', [$stockId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['product']);
            }
        }
    }

    public function testOrderDateIsSplitIntoTimestampAndStoredDateAndRetryPreservesValues(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562306893);

            $orderId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $versionId = Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210');
            $orderTable = $connection->quoteSingleIdentifier('order');
            $connection->executeStatement(<<<SQL
                INSERT INTO {$orderTable} (
                    id, version_id, state_id, order_number, currency_id, sales_channel_id,
                    billing_address_id, billing_address_version_id, price, order_date,
                    shipping_costs, created_at
                ) VALUES (
                    ?, ?, ?, ?, (SELECT id FROM currency LIMIT 1), (SELECT id FROM sales_channel LIMIT 1),
                    ?, ?, ?, ?, ?, '2020-01-01 00:00:00.000'
                )
                SQL, [
                $orderId,
                $versionId,
                Uuid::fromHexToBytes('abcdef0123456789abcdef0123456789'),
                'migration-order-date',
                Uuid::fromHexToBytes('abcdef0123456789abcdef0123456788'),
                $versionId,
                '{"totalPrice":1,"netPrice":1,"positionPrice":1}',
                '2020-05-04 12:34:56.789',
                '{"totalPrice":0}',
            ], [
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ]);

            static::assertSame([Migration1562324772AddOrderDateToOrder::class], $collection->migrateInPlace(1562324772));
            if ($connection === $this->postgres) {
                $timestampAndDate = $connection->fetchAssociative(
                    'SELECT to_char(order_date_time, \'YYYY-MM-DD HH24:MI:SS.MS\') AS date_time, to_char(order_date, \'YYYY-MM-DD\') AS order_date FROM "order" WHERE id = ? AND version_id = ?',
                    [$orderId, $versionId],
                    [ParameterType::BINARY, ParameterType::BINARY],
                );
                static::assertSame(['date_time' => '2020-05-04 12:34:56.789', 'order_date' => '2020-05-04'], $timestampAndDate);

                $variant = (new MigrationVariantRegistry())->resolve(Migration1562324772AddOrderDateToOrder::class, 1562324772);
                $schema = $this->schema($connection)['order'];
                $variant->update($connection);
                static::assertSame($timestampAndDate, $connection->fetchAssociative(
                    'SELECT to_char(order_date_time, \'YYYY-MM-DD HH24:MI:SS.MS\') AS date_time, to_char(order_date, \'YYYY-MM-DD\') AS order_date FROM "order" WHERE id = ? AND version_id = ?',
                    [$orderId, $versionId],
                    [ParameterType::BINARY, ParameterType::BINARY],
                ));
                static::assertSame($schema, $this->schema($connection)['order']);
            } else {
                static::assertSame(['2020-05-04 12:34:56.789000', '2020-05-04'], $connection->fetchNumeric('SELECT DATE_FORMAT(order_date_time, \'%Y-%m-%d %H:%i:%s.%f\'), DATE_FORMAT(order_date, \'%Y-%m-%d\') FROM `order` WHERE id = ? AND version_id = ?', [$orderId, $versionId], [ParameterType::BINARY, ParameterType::BINARY]));
            }
        }
    }

    public function testCustomerFirstLoginDateBecomesMidnightTimestampAndRetryPreservesData(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562240231);

            $customerId = Uuid::fromHexToBytes('1029384756abcdef1029384756abcdef');
            $connection->executeStatement(<<<'SQL'
                INSERT INTO customer (
                    id, customer_group_id, default_payment_method_id, sales_channel_id, language_id,
                    default_billing_address_id, default_shipping_address_id, customer_number, salutation_id,
                    first_name, last_name, email, first_login, created_at
                ) VALUES (
                    ?, (SELECT id FROM customer_group LIMIT 1), (SELECT id FROM payment_method LIMIT 1),
                    (SELECT id FROM sales_channel LIMIT 1), (SELECT id FROM language LIMIT 1), ?, ?, ?,
                    (SELECT id FROM salutation LIMIT 1), ?, ?, ?, ?, '2020-01-01 00:00:00.000'
                )
                SQL, [
                $customerId,
                Uuid::fromHexToBytes('abcdef0123456789abcdef0123456789'),
                Uuid::fromHexToBytes('fedcba9876543210fedcba9876543210'),
                'migration-first-login',
                'Test',
                'Customer',
                'migration-first-login@example.com',
                '2019-04-20',
            ], [
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::BINARY,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ]);

            static::assertSame([Migration1562306893MakeCustomerFirstLoginDateTime::class], $collection->migrateInPlace(1562306893));
            if ($connection === $this->postgres) {
                static::assertSame('2019-04-20 00:00:00.000', $connection->fetchOne('SELECT to_char(first_login, \'YYYY-MM-DD HH24:MI:SS.MS\') FROM customer WHERE id = ?', [$customerId], [ParameterType::BINARY]));
                $variant = (new MigrationVariantRegistry())->resolve(Migration1562306893MakeCustomerFirstLoginDateTime::class, 1562306893);
                $schema = $this->schema($connection)['customer'];
                $variant->update($connection);
                static::assertSame('2019-04-20 00:00:00.000', $connection->fetchOne('SELECT to_char(first_login, \'YYYY-MM-DD HH24:MI:SS.MS\') FROM customer WHERE id = ?', [$customerId], [ParameterType::BINARY]));
                static::assertSame($schema, $this->schema($connection)['customer']);
            } else {
                static::assertSame('2019-04-20 00:00:00.000000', $connection->fetchOne('SELECT DATE_FORMAT(first_login, \'%Y-%m-%d %H:%i:%s.%f\') FROM customer WHERE id = ?', [$customerId], [ParameterType::BINARY]));
            }
        }
    }

    public function testUserPasswordRecoveryMailIsSeededOnceAndRetryPreservesIt(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1562228335);

            static::assertSame([Migration1562240231UserPasswordRecovery::class], $collection->migrateInPlace(1562240231));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = \'user.recovery.request\''));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'user.recovery.request\')'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = \'user.recovery.request\''));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM mail_template_translation WHERE subject = \'Password recovery\' AND mail_template_id IN (SELECT id FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'user.recovery.request\'))'));
        }

        $variant = (new MigrationVariantRegistry())->resolve(Migration1562240231UserPasswordRecovery::class, 1562240231);
        $templateId = $this->postgres->fetchOne('SELECT encode(id, \'hex\') FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'user.recovery.request\')');
        $variant->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name = \'user.recovery.request\''));
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'user.recovery.request\')'));
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM event_action WHERE event_name = \'user.recovery.request\''));
        static::assertSame($templateId, $this->postgres->fetchOne('SELECT encode(id, \'hex\') FROM mail_template WHERE mail_template_type_id = (SELECT id FROM mail_template_type WHERE technical_name = \'user.recovery.request\')'));
    }

    public function testCurrencyPriceColumnIsRemovedWithoutLosingTheRemainingSchema(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(1558082916);
        $before = $this->schema($this->postgres)['product_price'];
        static::assertContains('currency_id', array_column($before['columns'], 'name'));

        $variant = (new MigrationVariantRegistry())->resolve(Migration1558105657CurrencyPrices::class, 1558105657);
        $variant->update($this->postgres);
        $after = $this->schema($this->postgres)['product_price'];
        static::assertNotContains('currency_id', array_column($after['columns'], 'name'));
        static::assertContains('price', array_column($after['columns'], 'name'));
        static::assertNotContains('fk.product_price.currency_id', array_column($after['foreignKeys'], 0));

        $variant->update($this->postgres);
        static::assertSame($after, $this->schema($this->postgres)['product_price']);
    }

    public function testPromotionSalesChannelForeignKeysCascadeAfterUpgradeAndRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1558443337);
            $foreignKeys = $this->schema($connection)['promotion_sales_channel']['foreignKeys'];
            foreach ($foreignKeys as $foreignKey) {
                if (\in_array($foreignKey[0], ['fk.promotion_sales_channel.promotion_id', 'fk.promotion_sales_channel.sales_channel_id'], true)) {
                    static::assertSame('CASCADE', $foreignKey[3]);
                    static::assertSame('CASCADE', $foreignKey[4]);
                }
            }
            static::assertCount(2, array_filter($foreignKeys, static fn (array $foreignKey): bool => \in_array($foreignKey[0], ['fk.promotion_sales_channel.promotion_id', 'fk.promotion_sales_channel.sales_channel_id'], true)));

            $schema = $this->schema($connection)['promotion_sales_channel'];
            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1558443337PromotionSalesChannel::class, 1558443337);
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection)['promotion_sales_channel']);
            }
        }
    }

    public function testConfigDefaultsAreInsertedOnlyWhenMissingAndRetrySafely(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1561712450);
            static::assertSame([Migration1562228335SetConfigDefaults::class], $collection->migrateInPlace(1562228335));

            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.basicInformation.shopName\' AND sales_channel_id IS NULL'));
            static::assertSame('Demostore', json_decode((string) $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.basicInformation.shopName\' AND sales_channel_id IS NULL'), true, flags: \JSON_THROW_ON_ERROR)['_value']);
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.listing.allowBuyInListing\' AND sales_channel_id IS NULL'));
            static::assertTrue(json_decode((string) $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = \'core.listing.allowBuyInListing\' AND sales_channel_id IS NULL'), true, flags: \JSON_THROW_ON_ERROR)['_value']);
        }

        $this->postgres->update('system_config', ['configuration_value' => '{"_value":"Custom shop"}'], ['configuration_key' => 'core.basicInformation.shopName']);
        (new MigrationVariantRegistry())->resolve(Migration1562228335SetConfigDefaults::class, 1562228335)->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.listing.allowBuyInListing\' AND sales_channel_id IS NULL'));
        static::assertSame('Custom shop', $this->postgres->fetchOne('SELECT configuration_value ->> \'_value\' FROM system_config WHERE configuration_key = \'core.basicInformation.shopName\' AND sales_channel_id IS NULL'));
    }

    public function testNewSystemConfigDefaultsPreserveExistingValuesAndRetrySafely(): void
    {
        $defaults = [
            'core.cart.maxQuantity' => '100',
            'core.loginRegistration.passwordMinLength' => '8',
            'core.address.showZipcodeInFrontOfCity' => true,
        ];

        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1561452005);
            $connection->insert('system_config', [
                'id' => str_repeat('P', 16),
                'configuration_key' => 'core.loginRegistration.passwordMinLength',
                'configuration_value' => '{"_value":"12"}',
                'created_at' => '2020-01-01 00:00:00.123',
            ], [ParameterType::BINARY]);
            static::assertSame([Migration1561712450NewSystemConfigsAndDefaultValues::class], $collection->migrateInPlace(1561712450));

            foreach ($defaults as $key => $expected) {
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]));
                $configuration = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]);
                static::assertIsString($configuration);
                $value = json_decode($configuration, true, flags: \JSON_THROW_ON_ERROR)['_value'];
                static::assertSame($key === 'core.loginRegistration.passwordMinLength' ? '12' : $expected, $value);
            }
        }

        (new MigrationVariantRegistry())->resolve(Migration1561712450NewSystemConfigsAndDefaultValues::class, 1561712450)->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.cart.maxQuantity\' AND sales_channel_id IS NULL'));
        static::assertSame('12', $this->postgres->fetchOne('SELECT configuration_value ->> \'_value\' FROM system_config WHERE configuration_key = \'core.loginRegistration.passwordMinLength\' AND sales_channel_id IS NULL'));
    }

    public function testUpdateDefaultsAreSeededOnceAndPreserveExistingConfiguration(): void
    {
        $defaults = [
            'core.update.apiUri' => 'https://update-api.shopware.com',
            'core.update.channel' => 'ea',
            'core.update.code' => '',
        ];

        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1561442979);
            static::assertSame([Migration1561452005Update::class], $collection->migrateInPlace(1561452005));
            foreach ($defaults as $key => $value) {
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]));
                $configuration = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]);
                static::assertIsString($configuration);
                static::assertSame($value, json_decode($configuration, true, flags: \JSON_THROW_ON_ERROR)['_value']);
            }
        }

        $this->postgres->update('system_config', ['configuration_value' => '{"_value":"custom-channel"}'], ['configuration_key' => 'core.update.channel']);
        (new MigrationVariantRegistry())->resolve(Migration1561452005Update::class, 1561452005)->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.update.channel\' AND sales_channel_id IS NULL'));
        static::assertSame('custom-channel', $this->postgres->fetchOne('SELECT configuration_value ->> \'_value\' FROM system_config WHERE configuration_key = \'core.update.channel\' AND sales_channel_id IS NULL'));
    }

    public function testMovedElasticsearchIndexMigrationIsARegisteredNoOp(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $migration = (new MigrationVariantRegistry())->resolve(Migration1561442979ElasticsearchIndexTask::class, 1561442979);
        static::assertSame(Migration1561442979ElasticsearchIndexTask::class, $migration::class);
        $tables = $this->tableNames($this->postgres);

        $migration->update($this->postgres);

        static::assertSame($tables, $this->tableNames($this->postgres));
    }

    public function testCountriesBecomeShippingAvailableByDefaultAndRetryPreservesOptOut(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1561370284);
            $countryId = str_repeat('K', 16);
            $connection->insert('country', ['id' => $countryId, 'active' => 0, 'created_at' => '2020-01-01 00:00:00.123'], [ParameterType::BINARY]);
            static::assertSame([Migration1561377793AddAvailableAsShippingCountry::class], $collection->migrateInPlace(1561377793));
            static::assertSame(1, (int) $connection->fetchOne('SELECT shipping_available FROM country WHERE id = ?', [$countryId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT active FROM country WHERE id = ?', [$countryId], [ParameterType::BINARY]));
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM country WHERE shipping_available <> 1'));

            if ($connection === $this->postgres) {
                $connection->update('country', ['shipping_available' => 0], ['id' => $countryId], [ParameterType::INTEGER, ParameterType::BINARY]);
                (new MigrationVariantRegistry())->resolve(Migration1561377793AddAvailableAsShippingCountry::class, 1561377793)->update($connection);
                static::assertSame(0, (int) $connection->fetchOne('SELECT shipping_available FROM country WHERE id = ?', [$countryId], [ParameterType::BINARY]));
            }
        }
    }

    public function testDefaultProductProfileMappingIsSeededOnceAndPreservesCustomizations(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1559306391);
            static::assertSame([Migration1561370284AddImportExportProductProfile::class], $collection->migrateInPlace(1561370284));
            $profile = $connection->fetchAssociative('SELECT id, name, mapping FROM import_export_profile WHERE source_entity = \'product\' AND system_default = 1');
            static::assertIsArray($profile);
            static::assertSame('Default product', $profile['name']);
            static::assertIsString($profile['mapping']);
            $mapping = json_decode($profile['mapping'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertIsArray($mapping);
            static::assertCount(11, $mapping);
            static::assertSame(['name.de-DE', 'name.en-GB', 'stock', 'manufacturer.id', 'tax.id', 'price.net', 'price.gross', 'price.linked', 'productNumber', 'releaseDate', 'categories.id'], array_column($mapping, 'entityField'));
            static::assertSame(array_column($mapping, 'entityField'), array_column($mapping, 'fileField'));
            static::assertSame(array_fill(0, 11, []), array_column($mapping, 'valueSubstitutions'));
        }

        $profileId = Uuid::fromHexToBytes((string) $this->postgres->fetchOne('SELECT sw_uuid_hex(id) FROM import_export_profile WHERE source_entity = \'product\' AND system_default = 1'));
        $this->postgres->update('import_export_profile', ['name' => 'Customized product', 'mapping' => '[]'], ['id' => $profileId], [ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY]);
        (new MigrationVariantRegistry())->resolve(Migration1561370284AddImportExportProductProfile::class, 1561370284)->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM import_export_profile WHERE source_entity = \'product\' AND system_default = 1'));
        static::assertSame([['Customized product', '[]']], $this->postgres->fetchAllNumeric('SELECT name, mapping FROM import_export_profile WHERE source_entity = \'product\' AND system_default = 1'));
    }

    public function testPromotionIndividualCodesKeepUniqueCodesAndCascadeWithTheirPromotion(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1559134989);
            $promotionId = str_repeat('Z', 16);
            $movedPromotionId = str_repeat('W', 16);
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('promotion', ['id' => $promotionId, 'code' => 'INDIVIDUAL-CODES-CONTRACT', 'created_at' => $createdAt], [ParameterType::BINARY]);
            static::assertSame([Migration1559306391PromotionIndividualCode::class], $collection->migrateInPlace(1559306391));
            $tableSchema = $this->schema($connection)['promotion_individual_code'];
            static::assertContains(['UNIQUE', ['code']], $tableSchema['indexes']);
            static::assertContains('payload', array_column($tableSchema['columns'], 'name'));

            $individualCodeId = str_repeat('I', 16);
            $connection->insert('promotion_individual_code', [
                'id' => $individualCodeId,
                'promotion_id' => $promotionId,
                'code' => 'SUMMER-2026-001',
                'payload' => null,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);
            $connection->update('promotion', ['id' => $movedPromotionId], ['id' => $promotionId], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame(bin2hex($movedPromotionId), $connection->fetchOne('SELECT sw_uuid_hex(promotion_id) FROM promotion_individual_code WHERE id = ?', [$individualCodeId], [ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT payload FROM promotion_individual_code WHERE id = ?', [$individualCodeId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $variant = (new MigrationVariantRegistry())->resolve(Migration1559306391PromotionIndividualCode::class, 1559306391);
                $schema = $this->schema($connection)['promotion_individual_code'];
                $variant->update($connection);
                static::assertSame($schema, $this->schema($connection)['promotion_individual_code']);
                static::assertSame('SUMMER-2026-001', $connection->fetchOne('SELECT code FROM promotion_individual_code WHERE id = ?', [$individualCodeId], [ParameterType::BINARY]));
            }

            $connection->delete('promotion', ['id' => $movedPromotionId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_individual_code'));
        }
    }

    public function testPromotionIndividualCodeFieldsDefaultAndRemainUniqueAfterRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1559050903);
            $promotionId = str_repeat('Y', 16);
            $connection->insert('promotion', ['id' => $promotionId, 'code' => 'INDIVIDUAL-CODE-CONTRACT', 'created_at' => '2020-01-01 00:00:00.123'], [ParameterType::BINARY]);
            static::assertSame([Migration1559134989Promotion::class], $collection->migrateInPlace(1559134989));
            static::assertSame(0, (int) $connection->fetchOne('SELECT use_individual_codes FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT individual_code_pattern FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            $promotionSchema = $this->schema($connection)['promotion'];
            static::assertContains(['UNIQUE', ['individual_code_pattern']], $promotionSchema['indexes']);

            if ($connection === $this->postgres) {
                $connection->update('promotion', ['use_individual_codes' => 1, 'individual_code_pattern' => 'SPRING-{code}'], ['id' => $promotionId], [ParameterType::INTEGER, ParameterType::STRING, ParameterType::BINARY]);
                (new MigrationVariantRegistry())->resolve(Migration1559134989Promotion::class, 1559134989)->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT use_individual_codes FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
                static::assertSame('SPRING-{code}', $connection->fetchOne('SELECT individual_code_pattern FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            }
        }
    }

    public function testPromotionExclusionJsonIsAddedAndSurvivesRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1559050088);
            $promotionId = str_repeat('X', 16);
            $connection->insert('promotion', ['id' => $promotionId, 'code' => 'EXCLUSION-JSON-CONTRACT', 'created_at' => '2020-01-01 00:00:00.123'], [ParameterType::BINARY]);
            static::assertSame([Migration1559050903PromotionExclusion::class], $collection->migrateInPlace(1559050903));
            static::assertNull($connection->fetchOne('SELECT exclusion_ids FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            static::assertContains('exclusion_ids', array_column($this->schema($connection)['promotion']['columns'], 'name'));

            if ($connection === $this->postgres) {
                $connection->update('promotion', ['exclusion_ids' => '["customer", "cart"]'], ['id' => $promotionId], [ParameterType::STRING, ParameterType::BINARY]);
                (new MigrationVariantRegistry())->resolve(Migration1559050903PromotionExclusion::class, 1559050903)->update($connection);
                static::assertSame(['customer', 'cart'], json_decode((string) $connection->fetchOne('SELECT exclusion_ids FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]), true, flags: \JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testPromotionCustomerRestrictionDefaultsAndSurvivesRetry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1558938938);
            $promotionId = str_repeat('Q', 16);
            $connection->insert('promotion', ['id' => $promotionId, 'code' => 'CUSTOMER-RESTRICTION-CONTRACT', 'created_at' => '2020-01-01 00:00:00.123'], [ParameterType::BINARY]);
            static::assertSame([Migration1559050088Promotion::class], $collection->migrateInPlace(1559050088));
            static::assertSame(0, (int) $connection->fetchOne('SELECT customer_restriction FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            static::assertContains('customer_restriction', array_column($this->schema($connection)['promotion']['columns'], 'name'));

            if ($connection === $this->postgres) {
                $connection->update('promotion', ['customer_restriction' => 1], ['id' => $promotionId], [ParameterType::INTEGER, ParameterType::BINARY]);
                (new MigrationVariantRegistry())->resolve(Migration1559050088Promotion::class, 1559050088)->update($connection);
                static::assertSame(1, (int) $connection->fetchOne('SELECT customer_restriction FROM promotion WHERE id = ?', [$promotionId], [ParameterType::BINARY]));
            }
        }
    }

    public function testConfiguratorSettingsReplaceTheOldSortingColumnAndSetListingDefault(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1558594334);
            $productId = str_repeat('S', 16);
            $versionId = str_repeat('V', 16);
            $createdAt = '2020-01-01 00:00:00.123';
            $connection->insert('product', [
                'id' => $productId,
                'version_id' => $versionId,
                'product_number' => 'CONFIGURATOR-CONTRACT',
                'configurator_group_sorting' => '["color"]',
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);

            static::assertSame([Migration1558938938ChangeGroupSortingColumn::class], $collection->migrateInPlace(1558938938));
            $columns = array_column($this->schema($connection)['product']['columns'], 'name');
            static::assertNotContains('configurator_group_sorting', $columns);
            static::assertContains('configurator_group_config', $columns);
            static::assertContains('display_in_listing', $columns);
            static::assertSame('CONFIGURATOR-CONTRACT', $connection->fetchOne('SELECT product_number FROM product WHERE id = ?', [$productId], [ParameterType::BINARY]));
            static::assertNull($connection->fetchOne('SELECT configurator_group_config FROM product WHERE id = ?', [$productId], [ParameterType::BINARY]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT display_in_listing FROM product WHERE id = ?', [$productId], [ParameterType::BINARY]));

            if ($connection === $this->postgres) {
                $schema = $this->schema($connection)['product'];
                (new MigrationVariantRegistry())->resolve(Migration1558938938ChangeGroupSortingColumn::class, 1558938938)->update($connection);
                static::assertSame($schema, $this->schema($connection)['product']);
                static::assertSame('CONFIGURATOR-CONTRACT', $connection->fetchOne('SELECT product_number FROM product WHERE id = ?', [$productId], [ParameterType::BINARY]));
            }
        }
    }

    public function testPromotionDiscountPricesCascadeForDiscountAndCurrencyChanges(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1558594334);
            $createdAt = '2020-01-01 00:00:00.123';
            $promotionId = str_repeat('P', 16);
            $discountId = str_repeat('D', 16);
            $movedDiscountId = str_repeat('E', 16);
            $currencyId = str_repeat('C', 16);
            $movedCurrencyId = str_repeat('F', 16);
            $priceId = str_repeat('R', 16);
            $connection->insert('currency', [
                'id' => $currencyId,
                'iso_code' => 'ZZZ',
                'factor' => 1,
                'symbol' => 'Z',
                'decimal_precision' => 2,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY]);
            $connection->insert('promotion', ['id' => $promotionId, 'created_at' => $createdAt], [ParameterType::BINARY]);
            $connection->insert('promotion_discount', [
                'id' => $discountId,
                'promotion_id' => $promotionId,
                'scope' => 'cart',
                'type' => 'percentage',
                'value' => 10,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);
            $connection->insert('promotion_discount_prices', [
                'id' => $priceId,
                'discount_id' => $discountId,
                'currency_id' => $currencyId,
                'price' => 12.5,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]);

            $connection->update('promotion_discount', ['id' => $movedDiscountId], ['id' => $discountId], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame(bin2hex($movedDiscountId), $connection->fetchOne('SELECT sw_uuid_hex(discount_id) FROM promotion_discount_prices'));
            $connection->update('currency', ['id' => $movedCurrencyId], ['id' => $currencyId], [ParameterType::BINARY, ParameterType::BINARY]);
            static::assertSame(bin2hex($movedCurrencyId), $connection->fetchOne('SELECT sw_uuid_hex(currency_id) FROM promotion_discount_prices'));

            $connection->delete('promotion_discount', ['id' => $movedDiscountId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount_prices'));

            $secondDiscountId = str_repeat('G', 16);
            $secondPriceId = str_repeat('H', 16);
            $connection->insert('promotion_discount', [
                'id' => $secondDiscountId,
                'promotion_id' => $promotionId,
                'scope' => 'cart',
                'type' => 'percentage',
                'value' => 5,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);
            $connection->insert('promotion_discount_prices', [
                'id' => $secondPriceId,
                'discount_id' => $secondDiscountId,
                'currency_id' => $movedCurrencyId,
                'price' => 7.5,
                'created_at' => $createdAt,
            ], [ParameterType::BINARY, ParameterType::BINARY, ParameterType::BINARY]);
            $connection->delete('currency', ['id' => $movedCurrencyId], [ParameterType::BINARY]);
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM promotion_discount_prices'));
        }
    }

    public function testLoggingDefaultsAreSeededOnceAndPreserveExistingValues(): void
    {
        $keys = [
            'core.logging.cleanupInterval' => '86400',
            'core.logging.entryLimit' => '10000000',
            'core.logging.entryLifetimeSeconds' => '2678400',
        ];

        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1558443337);
            static::assertSame([Migration1558505525Logging::class], $collection->migrateInPlace(1558505525));
            foreach ($keys as $key => $expectedValue) {
                static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]));
                $configuration = $connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]);
                static::assertIsString($configuration);
                static::assertSame($expectedValue, json_decode($configuration, true, flags: \JSON_THROW_ON_ERROR)['_value']);
            }
        }

        $this->postgres->update('system_config', ['configuration_value' => '{"_value":"custom"}'], ['configuration_key' => 'core.logging.entryLimit']);
        (new MigrationVariantRegistry())->resolve(Migration1558505525Logging::class, 1558505525)->update($this->postgres);
        static::assertSame(1, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM system_config WHERE configuration_key = \'core.logging.entryLimit\' AND sales_channel_id IS NULL'));
        static::assertSame('custom', $this->postgres->fetchOne('SELECT configuration_value ->> \'_value\' FROM system_config WHERE configuration_key = \'core.logging.entryLimit\' AND sales_channel_id IS NULL'));
    }

    public function testImportProfileRetainsValidUuidSubstitutionsAndCustomizations(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1554199340);
            $profile = $connection->fetchAssociative('SELECT name, source_entity, file_type, delimiter, mapping FROM import_export_profile WHERE system_default = 1');
            static::assertIsArray($profile);
            static::assertSame(['Default customer', 'customer', 'text/csv', ';'], [$profile['name'], $profile['source_entity'], $profile['file_type'], $profile['delimiter']]);
            static::assertIsString($profile['mapping']);
            $mapping = json_decode($profile['mapping'], true, flags: \JSON_THROW_ON_ERROR);
            static::assertIsArray($mapping);
            static::assertCount(25, $mapping);
            $fields = array_column($mapping, 'valueSubstitutions', 'entityField');
            static::assertSame([false, true], $fields['guest']);
            static::assertSame('cfbd5018d38d41d8adca10d94fc8bdd6', $fields['groupId']['default']);
            static::assertSame('98432def39fc4624b33213a56b8c944d', $fields['salesChannelId']['default']);
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM country WHERE iso = \'DE\''),
                $fields['defaultBillingAddress.countryId']['DE']
            );
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM salutation WHERE salutation_key = \'mr\''),
                $fields['defaultShippingAddress.salutationId']['mr']
            );
            static::assertSame(
                $connection->fetchOne('SELECT sw_uuid_hex(id) FROM payment_method WHERE handler_identifier LIKE \'%CashPayment\''),
                $fields['defaultPaymentMethodId']['cash']
            );
        }

        $this->postgres->executeStatement('UPDATE import_export_profile SET name = ?, mapping = ?', ['My customer profile', '[]']);
        (new MigrationVariantRegistry())->resolve(Migration1554199340AddImportExportProfile::class, 1554199340)->update($this->postgres);
        static::assertSame([['My customer profile', '[]']], $this->postgres->fetchAllNumeric('SELECT name, mapping FROM import_export_profile'));
    }

    public function testFileDeletionPreservesTheHistoryEntry(): void
    {
        foreach ([$this->mysql, $this->postgres] as $connection) {
            $collection = $this->collection($connection);
            $collection->sync();
            $collection->migrateInPlace(1554203706);

            $id = str_repeat('F', 16);
            $loggedAt = '2020-01-01 00:00:00.123';
            $connection->insert('import_export_file', [
                'id' => $id,
                'original_name' => 'customers.csv',
                'path' => 'customers.csv',
                'expire_date' => '2020-01-02 00:00:00.123',
                'created_at' => $loggedAt,
                'access_token' => 'test-token',
            ], [ParameterType::BINARY]);
            $connection->insert('import_export_log', [
                'id' => $id,
                'file_id' => $id,
                'activity' => 'import',
                'state' => 'complete',
                'records' => 12,
                'created_at' => $loggedAt,
            ], [ParameterType::BINARY, ParameterType::BINARY]);
            $connection->delete('import_export_file', ['id' => $id], [ParameterType::BINARY]);
            static::assertSame([['complete', 12, null]], $connection->fetchAllNumeric('SELECT state, records, file_id FROM import_export_log'));
        }
    }

    public function testHistoricalReviewResetAndIdempotentColumnChangesMatchSourceSemantics(): void
    {
        $collection = $this->collection($this->postgres);
        $collection->sync();
        $collection->migrateInPlace(self::LAST_TIMESTAMP);
        $id = str_repeat('R', 16);
        $version = str_repeat('V', 16);
        $created = '2020-01-01 00:00:00.123';
        $this->postgres->insert('product', ['id' => $id, 'version_id' => $version, 'stock' => 0, 'rating_average' => 4.5, 'created_at' => $created], [ParameterType::BINARY, ParameterType::BINARY]);
        $this->postgres->insert('product_review', ['id' => $id, 'product_id' => $id, 'product_version_id' => $version, 'title' => 'Historical customer review', 'created_at' => $created], array_fill(0, 3, ParameterType::BINARY));
        $this->postgres->executeStatement('UPDATE category_translation SET breadcrumb = ?', ['{"current":"Catalogue"}']);
        $this->postgres->executeStatement('UPDATE media_folder_configuration SET no_association = 1');

        $registry = new MigrationVariantRegistry();
        $registry->resolve(Migration1552360944MediaFolderConfigurationNoAssoc::class, 1552360944)->update($this->postgres);
        $registry->resolve(Migration1554900301AddReviewTable::class, 1554900301)->update($this->postgres);
        $registry->resolve(Migration1556809270AddAverageRatingToProduct::class, 1556809270)->update($this->postgres);
        $registry->resolve(Migration1558082916AddBreadcrumb::class, 1558082916)->update($this->postgres);

        static::assertSame(0, (int) $this->postgres->fetchOne('SELECT COUNT(*) FROM product_review'), 'The original update drops product_review before recreating it.');
        static::assertSame(4.5, $this->postgres->fetchOne('SELECT rating_average FROM product'));
        static::assertSame(['Catalogue', 'Catalogue'], $this->postgres->fetchFirstColumn('SELECT breadcrumb ->> \'current\' FROM category_translation'));
        static::assertSame([1], $this->postgres->fetchFirstColumn('SELECT DISTINCT no_association FROM media_folder_configuration'));
    }

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, primaryKey: list<string>|null, foreignKeys: list<array<mixed>>, indexes: list<array<mixed>>}>
     */
    private function schema(Connection $connection): array
    {
        $columns = $this->columns($connection);
        $indexes = $this->indexes($connection);
        $tables = [];
        foreach ($connection->createSchemaManager()->introspectTables() as $table) {
            $name = $table->getObjectName();
            $tableName = $name->getUnqualifiedName()->getValue();
            if ($name->getQualifier() !== null || !isset($columns[$tableName])) {
                continue;
            }

            $primaryKey = $table->getPrimaryKeyConstraint();
            $tables[$tableName] = [
                'columns' => $columns[$tableName],
                'primaryKey' => $primaryKey === null ? null : array_map(
                    static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
                    $primaryKey->getColumnNames()
                ),
                'foreignKeys' => array_values(array_map($this->foreignKeySignature(...), $table->getForeignKeys())),
                'indexes' => array_values($indexes[$tableName] ?? []),
            ];
            sort($tables[$tableName]['foreignKeys']);
            sort($tables[$tableName]['indexes']);
        }
        ksort($tables);

        return $tables;
    }

    /**
     * @return array<string, array<string, array{string, list<string>}>>
     */
    private function indexes(Connection $connection): array
    {
        // DBAL can synthesize an index from a foreign key while building a Table.
        // Read the physical indexes so additional indexes and their column order are observable.
        $sql = $connection === $this->postgres
            ? <<<'SQL'
                SELECT t.relname AS table_name, idx.relname AS index_name, a.attname AS column_name,
                       CASE WHEN i.indisunique THEN 'UNIQUE' ELSE 'REGULAR' END AS index_type
                FROM pg_catalog.pg_index i
                JOIN pg_catalog.pg_class t ON t.oid = i.indrelid
                JOIN pg_catalog.pg_namespace n ON n.oid = t.relnamespace
                JOIN pg_catalog.pg_class idx ON idx.oid = i.indexrelid
                CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS part(attribute_number, index_position)
                JOIN pg_catalog.pg_attribute a ON a.attrelid = t.oid AND a.attnum = part.attribute_number
                WHERE n.nspname = current_schema() AND NOT i.indisprimary
                ORDER BY t.relname, idx.relname, part.index_position
                SQL
            : <<<'SQL'
                SELECT table_name AS table_name, index_name AS index_name, column_name AS column_name,
                       CASE WHEN non_unique = 0 THEN 'UNIQUE' ELSE 'REGULAR' END AS index_type
                FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND index_name <> 'PRIMARY'
                ORDER BY table_name, index_name, seq_in_index
                SQL;
        $tables = [];
        foreach ($connection->fetchAllAssociative($sql) as $row) {
            $tableName = (string) $row['table_name'];
            $indexName = (string) $row['index_name'];
            $tables[$tableName][$indexName] ??= [(string) $row['index_type'], []];
            $tables[$tableName][$indexName][1][] = (string) $row['column_name'];
        }

        return $tables;
    }

    /**
     * @return array{?string, array<string, string>, string, string, string}
     */
    private function foreignKeySignature(ForeignKeyConstraint $key): array
    {
        $column = static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue();
        $pairs = array_combine(
            array_map($column, $key->getReferencingColumnNames()),
            array_map($column, $key->getReferencedColumnNames())
        );
        ksort($pairs);

        return [
            $key->getObjectName()?->getIdentifier()->getValue(),
            $pairs,
            $key->getReferencedTableName()->getUnqualifiedName()->getValue(),
            $key->getOnDeleteAction()->name,
            $key->getOnUpdateAction()->name,
        ];
    }

    private function normalizeBinaryValue(mixed $value): ?string
    {
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return \is_string($value) ? $value : null;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function columns(Connection $connection): array
    {
        $postgres = $connection === $this->postgres;
        $schema = $postgres ? 'current_schema()' : 'DATABASE()';
        $nativeType = $postgres ? 'data_type' : 'column_type';
        $identity = $postgres
            ? 'pg_get_serial_sequence(quote_ident(table_name), column_name) IS NOT NULL'
            : 'extra = \'auto_increment\'';
        $generated = $postgres
            ? 'CASE WHEN is_generated = \'ALWAYS\' THEN (
                    SELECT attr.attgenerated::text
                    FROM pg_catalog.pg_attribute AS attr
                    WHERE attr.attrelid = to_regclass(quote_ident(column_info.table_name))
                      AND attr.attname = column_info.column_name AND attr.attnum > 0
                ) ELSE \'\' END'
            : 'CASE extra WHEN \'VIRTUAL GENERATED\' THEN \'v\' WHEN \'STORED GENERATED\' THEN \'s\' ELSE \'\' END';
        $columns = $connection->fetchAllAssociative(
            "SELECT table_name AS table_name, column_name AS name, data_type AS type, $nativeType AS native_type, character_maximum_length AS length,
                    numeric_precision AS number_precision, numeric_scale AS scale, datetime_precision AS time_precision,
                    collation_name AS collation, is_nullable AS nullable, column_default AS default_value,
                    CASE WHEN $identity THEN 1 ELSE 0 END AS identity_column,
                    $generated AS generated_column
             FROM information_schema.columns AS column_info
             WHERE table_schema = $schema AND table_name NOT IN ('migration', 'plugin')",
        );

        $tables = [];
        foreach ($columns as $column) {
            $table = (string) $column['table_name'];
            unset($column['table_name']);
            $type = (string) $column['type'];
            $nativeType = (string) $column['native_type'];
            unset($column['native_type']);
            $unsignedSequence = (int) $column['identity_column'] === 1 && $nativeType === 'bigint unsigned';
            $column['type'] = match ($type) {
                'bigint' => $unsignedSequence ? 'numeric' : 'bigint',
                'binary', 'longblob' => 'bytea',
                'character' => 'char',
                'character varying' => 'varchar',
                'datetime' => 'timestamp without time zone',
                'decimal' => 'numeric',
                'double' => 'double precision',
                'float' => 'real',
                'int' => str_ends_with($nativeType, ' unsigned') ? 'bigint' : 'integer',
                'json' => 'jsonb',
                'longtext', 'mediumtext' => 'text',
                'tinyint' => 'smallint',
                default => $type,
            };
            $column['length'] = \in_array($type, ['char', 'character', 'character varying', 'varchar'], true) ? (int) $column['length'] : null;
            $column['number_precision'] = $unsignedSequence ? 20 : (\in_array($type, ['decimal', 'numeric'], true) ? (int) $column['number_precision'] : null);
            $column['scale'] = $column['type'] === 'numeric' ? (int) $column['scale'] : null;
            $column['time_precision'] = $column['time_precision'] === null || $type === 'date' ? null : (int) $column['time_precision'];
            $column['collation'] = match ($column['collation']) {
                'sw_type_ci', 'utf8mb3_unicode_ci', 'utf8_unicode_ci', 'utf8mb4_unicode_ci', 'utf8mb4_0900_ai_ci' => 'shopware_ci',
                default => $column['collation'],
            };
            $column['identity_column'] = (int) $column['identity_column'];
            if ($column['identity_column'] === 1) {
                $column['default_value'] = null;
            } elseif ($postgres && \is_string($column['default_value'])) {
                $literal = explode('::', $column['default_value'], 2)[0];
                $column['default_value'] = $literal === 'NULL' ? null : trim($literal, '\'');
            } elseif ($column['default_value'] !== null) {
                $column['default_value'] = (string) $column['default_value'];
            }
            $tables[$table][] = $column;
        }
        foreach ($tables as &$tableColumns) {
            usort($tableColumns, static fn (array $first, array $second): int => strcmp((string) $first['name'], (string) $second['name']));
        }

        return $tables;
    }
}
