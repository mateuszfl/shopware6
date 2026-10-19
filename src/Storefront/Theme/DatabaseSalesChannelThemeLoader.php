<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('discovery')]
/**
 * @internal
 *
 * @final
 */
class DatabaseSalesChannelThemeLoader
{
    /**
     * @var array<string, list<string>>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<string>
     */
    public function load(string $salesChannelId): array
    {
        if (($this->themes[$salesChannelId] ?? []) !== []) {
            return $this->themes[$salesChannelId];
        }

        return $this->themes[$salesChannelId] = $this->readFromDB($salesChannelId);
    }

    public function reset(): void
    {
        $this->themes = [];
    }

    /**
     * @return list<string>
     */
    private function readFromDB(string $salesChannelId): array
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $configInheritance = $dialect->jsonExtract(['theme', 'base_config'], ['configInheritance'])->named();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ' . $dialect->hex(['theme', 'id']) . ' AS ' . $dialect->quoteIdentifier('themeId') . ',
                    theme.technical_name AS ' . $dialect->quoteIdentifier('technicalName') . ',
                    ' . $dialect->hex(['theme', 'parent_theme_id']) . ' AS ' . $dialect->quoteIdentifier('parentThemeId') . ',
                    ' . $configInheritance->sql . ' AS ' . $dialect->quoteIdentifier('configInheritance') . ',
                    theme_sales_channel.sales_channel_id IS NOT NULL AS assigned
            FROM theme
                LEFT JOIN theme_sales_channel
                    ON theme_sales_channel.theme_id = theme.id
                    AND theme_sales_channel.sales_channel_id = :salesChannelId',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
            ] + $configInheritance->parameters,
            ['salesChannelId' => ParameterType::BINARY] + $configInheritance->types
        );

        $themesById = [];
        $idsByTechnicalName = [];
        $assignedThemeId = null;

        foreach ($rows as $row) {
            $themeId = (string) $row['themeId'];
            $themesById[$themeId] = $row;

            if (\is_string($row['technicalName'])) {
                $idsByTechnicalName[$row['technicalName']] = $themeId;
            }

            if ($assignedThemeId === null && (int) $row['assigned'] === 1) {
                $assignedThemeId = $themeId;
            }
        }

        if ($assignedThemeId === null) {
            return [];
        }

        $technicalNames = [];
        $visited = [$assignedThemeId => true];
        $queue = [$assignedThemeId];

        while (($themeId = array_shift($queue)) !== null) {
            $row = $themesById[$themeId];

            if (\is_string($row['technicalName'])) {
                $technicalNames[$row['technicalName']] = true;
            }

            foreach ($this->getAncestorIds($row, $idsByTechnicalName) as $ancestorId) {
                if (isset($visited[$ancestorId]) || !isset($themesById[$ancestorId])) {
                    continue;
                }

                $visited[$ancestorId] = true;
                $queue[] = $ancestorId;
            }
        }

        return array_keys($technicalNames);
    }

    /**
     * `parent_theme_id` holds one ancestor, `configInheritance` may name several. Both are sources.
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $idsByTechnicalName
     *
     * @return list<string>
     */
    private function getAncestorIds(array $row, array $idsByTechnicalName): array
    {
        $ancestorIds = [];

        // Must stay first: theme copies have no technical name, index 0 comes from the database parent.
        if (\is_string($row['parentThemeId'])) {
            $ancestorIds[] = $row['parentThemeId'];
        }

        $configInheritance = json_decode((string) $row['configInheritance'], true);

        if (!\is_array($configInheritance)) {
            return $ancestorIds;
        }

        // configInheritance is ordered from least to most specific
        foreach (array_reverse($configInheritance) as $technicalName) {
            if (!\is_string($technicalName)) {
                continue;
            }

            $ancestorId = $idsByTechnicalName[ltrim($technicalName, '@')] ?? null;

            if ($ancestorId !== null && $ancestorId !== $row['themeId']) {
                $ancestorIds[] = $ancestorId;
            }
        }

        return $ancestorIds;
    }
}
