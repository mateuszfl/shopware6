<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
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

/**
 * @internal
 */
#[Package('framework')]
class PostInitialSchemaPostgreSQLTest extends MigrationHistoryDatabaseTestCase
{
    private const BASIC_DATA_TIMESTAMP = 1536233560;
    private const LAST_TIMESTAMP = 1565705280;

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
        static::assertCount(41, $pending);
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
                'sw_type_ci', 'utf8mb4_unicode_ci', 'utf8mb4_0900_ai_ci' => 'shopware_ci',
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
