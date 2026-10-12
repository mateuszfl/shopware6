<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-import-type ResolvedSeoUrlArray from AbstractSeoResolver
 */
#[Package('inventory')]
class SeoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed in v6.8.0, use {@see resolveUrl()} instead
     *
     * @return ResolvedSeoUrlArray
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::resolveUrl()')
        );

        $resolved = $this->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, $pathInfo));

        $data = [
            'pathInfo' => $resolved->pathInfo,
            'isCanonical' => $resolved->isCanonical,
        ];

        if ($resolved->id !== null) {
            $data['id'] = $resolved->id;
        }

        if ($resolved->canonicalPathInfo !== null) {
            $data['canonicalPathInfo'] = $resolved->canonicalPathInfo;
        }

        if ($resolved->seoPathInfo !== null) {
            $data['seoPathInfo'] = $resolved->seoPathInfo;
        }

        return $data;
    }

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $seoPathInfo = trim($context->pathInfo, '/');
        $normalizedQueryString = $this->normalizeQueryString($context->queryString);

        $query = (new QueryBuilder($this->connection))
            ->select('id', 'path_info', 'seo_path_info', 'is_canonical', 'sales_channel_id')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('seo_url.is_deleted = 0');

        $seoPathConditions = [
            'seo_path_info = :seoPath',
            'seo_path_info = :seoPathWithSlash',
        ];

        $query->setParameter('language_id', Uuid::fromHexToBytes($context->languageId), ParameterType::BINARY)
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId), ParameterType::BINARY)
            ->setParameter('seoPath', $seoPathInfo)
            ->setParameter('seoPathWithSlash', $seoPathInfo . '/');

        $queryCandidates = array_values(array_unique(array_filter(
            [$normalizedQueryString, $context->queryString],
            static fn (?string $query): bool => $query !== null && $query !== ''
        )));

        foreach ($queryCandidates as $index => $candidate) {
            $seoPathConditions[] = "seo_path_info = :seoPathWithQuery{$index}";
            $seoPathConditions[] = "seo_path_info = :seoPathWithSlashAndQuery{$index}";
            $query->setParameter("seoPathWithQuery{$index}", $seoPathInfo . '?' . $candidate)
                ->setParameter("seoPathWithSlashAndQuery{$index}", $seoPathInfo . '/?' . $candidate);
        }

        $query->andWhere('(' . implode(' OR ', $seoPathConditions) . ')');
        $query->setTitle('seo-url::resolve');

        /** @var list<array{id: string, path_info: string, seo_path_info: string, is_canonical: string|null, sales_channel_id: string|null}> $seoPaths */
        $seoPaths = $query->executeQuery()->fetchAllAssociative();

        foreach ($seoPaths as &$path) {
            $path['id'] = Uuid::fromHexToBytes(Uuid::fromBytesToHex($path['id']));
        }
        unset($path);

        usort($seoPaths, function ($a, $b) use ($normalizedQueryString) {
            if ($a['is_canonical'] === null) {
                return 1;
            }

            if ($b['is_canonical'] === null) {
                return -1;
            }

            if ($a['sales_channel_id'] === null) {
                return 1;
            }

            if ($b['sales_channel_id'] === null) {
                return -1;
            }

            if ($normalizedQueryString !== null) {
                $aMatches = $this->storedQueryMatches($a['seo_path_info'], $normalizedQueryString);
                $bMatches = $this->storedQueryMatches($b['seo_path_info'], $normalizedQueryString);
                if ($aMatches !== $bMatches) {
                    return $aMatches ? -1 : 1;
                }
            }

            return 0;
        });

        $seoPath = ['path_info' => $seoPathInfo, 'is_canonical' => false];

        foreach ($seoPaths as $path) {
            $seoPath = $path;
            if ($path['is_canonical']) {
                break;
            }
        }

        if (!$seoPath['is_canonical']) {
            $query = (new QueryBuilder($this->connection))
                ->select('path_info', 'seo_path_info')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('sales_channel_id = :sales_channel_id')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_canonical = 1')
                ->andWhere('is_deleted = 0')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($context->languageId), ParameterType::BINARY)
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($context->salesChannelId), ParameterType::BINARY)
                ->setParameter('pathInfo', '/' . ltrim((string) $seoPath['path_info'], '/'));

            $query->setTitle('seo-url::resolve-fallback');

            // we only have an id when the hit seo url was not a canonical url, save the one filter condition
            if (isset($seoPath['id'])) {
                $query->andWhere('id != :id')
                    ->setParameter('id', $seoPath['id'], ParameterType::BINARY);
            }

            $canonicalQueryResult = $query->executeQuery()->fetchAssociative();
            if ($canonicalQueryResult) {
                $seoPath['canonicalPathInfo'] = '/' . ltrim((string) $canonicalQueryResult['seo_path_info'], '/');
            }
        }

        $seoPath['path_info'] = '/' . ltrim((string) $seoPath['path_info'], '/');

        return new ResolvedSeoUrl(
            pathInfo: $seoPath['path_info'],
            isCanonical: (bool) $seoPath['is_canonical'],
            id: $seoPath['id'] ?? null,
            canonicalPathInfo: $seoPath['canonicalPathInfo'] ?? null,
            seoPathInfo: $seoPath['seo_path_info'] ?? null,
        );
    }

    private function normalizeQueryString(?string $queryString): ?string
    {
        $normalizedQueryString = Request::normalizeQueryString($queryString);

        return $normalizedQueryString === '' ? null : $normalizedQueryString;
    }

    private function storedQueryMatches(mixed $storedSeoPathInfo, string $normalizedQueryString): bool
    {
        if (!\is_string($storedSeoPathInfo)) {
            return false;
        }

        $storedQuery = parse_url($storedSeoPathInfo, \PHP_URL_QUERY);
        if (!\is_string($storedQuery)) {
            return false;
        }

        return $this->normalizeQueryString($storedQuery) === $normalizedQueryString;
    }
}
