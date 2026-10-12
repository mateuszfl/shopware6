<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\Hreflang\HreflangStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;

#[Package('inventory')]
class HreflangLoader implements HreflangLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly Connection $connection
    ) {
    }

    public function load(HreflangLoaderParameter $parameter): HreflangCollection
    {
        $salesChannelContext = $parameter->getSalesChannelContext();

        if (!$salesChannelContext->getSalesChannel()->isHreflangActive()) {
            return new HreflangCollection();
        }

        $domains = $this->fetchSalesChannelDomains($salesChannelContext->getSalesChannelId());

        if ($parameter->isHomepage()) {
            return $this->getHreflangForHomepage($domains, $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId());
        }

        $pathInfo = $this->router->generate($parameter->getRoute(), $parameter->getRouteParameters(), RouterInterface::ABSOLUTE_PATH);

        $basePath = $parameter->getBasePath();
        if ($basePath !== '' && str_starts_with($pathInfo, $basePath)) {
            $pathInfo = mb_substr($pathInfo, mb_strlen($basePath));
        }

        $languageToDomainMapping = $this->getLanguageToDomainMapping($domains);
        $seoUrls = $this->fetchSeoUrls($pathInfo, $salesChannelContext->getSalesChannelId(), array_keys($languageToDomainMapping));

        // We need at least two links
        if (\count($seoUrls) <= 1) {
            return new HreflangCollection();
        }

        $hreflangCollection = new HreflangCollection();

        /** @var array{seoPathInfo: string, languageId: string} $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
            foreach ($languageToDomainMapping[$seoUrl['languageId']] as $domain) {
                $this->addHreflangForDomain(
                    $domain,
                    $seoUrl,
                    $salesChannelContext->getSalesChannel()->getHreflangDefaultDomainId(),
                    $hreflangCollection
                );
            }
        }

        return $hreflangCollection;
    }

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $domains
     */
    private function getHreflangForHomepage(array $domains, ?string $defaultDomainId): HreflangCollection
    {
        $collection = new HreflangCollection();

        if (\count($domains) <= 1) {
            return new HreflangCollection();
        }

        /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
        foreach ($domains as $domain) {
            $this->addHreflangForDomain(
                $domain,
                null,
                $defaultDomainId,
                $collection
            );
        }

        return $collection;
    }

    /**
     * @return list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}>
     */
    private function fetchSalesChannelDomains(string $salesChannelId): array
    {
        /** @var list<array{language_id: string, id: string, url: string, locale: string, only_locale: bool}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT sw_uuid_hex(domain.language_id) AS language_id,
                          sw_uuid_hex(domain.id) AS id,
                          domain.url,
                          domain.hreflang_use_only_locale AS only_locale,
                          locale.code AS locale
            FROM sales_channel_domain AS domain
            INNER JOIN language ON language.id = domain.language_id
            INNER JOIN locale ON locale.id = language.locale_id
            WHERE domain.sales_channel_id = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)],
            ['salesChannelId' => ParameterType::BINARY]
        );

        return array_map(static fn (array $row): array => ['languageId' => $row['language_id'], 'id' => $row['id'], 'url' => $row['url'], 'locale' => $row['locale'], 'onlyLocale' => (bool) $row['only_locale']], $result);
    }

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $domains
     *
     * @return array<string, list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}>>
     */
    private function getLanguageToDomainMapping(array $domains): array
    {
        $mapping = [];

        foreach ($domains as $domain) {
            $mapping[$domain['languageId']][] = $domain;
        }

        return $mapping;
    }

    /**
     * @param array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain
     * @param array{seoPathInfo: string, languageId: string}|null $seoUrl
     */
    private function addHreflangForDomain(
        array $domain,
        ?array $seoUrl,
        ?string $defaultDomainId,
        HreflangCollection $collection
    ): void {
        $hrefLang = new HreflangStruct();

        $hrefLang->setUrl($domain['url']);
        if ($seoUrl) {
            $hrefLang->setUrl($domain['url'] . '/' . $seoUrl['seoPathInfo']);
        }
        $locale = $domain['locale'];

        if ($domain['onlyLocale']) {
            $locale = mb_substr($locale, 0, 2);
        }

        if ($domain['id'] === $defaultDomainId) {
            $mainLang = clone $hrefLang;
            $mainLang->setLocale('x-default');
            $collection->add($mainLang);
        }

        $hrefLang->setLocale($locale);
        $collection->add($hrefLang);
    }

    /**
     * @param array<string> $languageIds
     *
     * @return list<array{seoPathInfo: string, languageId: string}>
     */
    private function fetchSeoUrls(string $pathInfo, string $salesChannelId, array $languageIds): array
    {
        /** @var list<array{seo_path_info: string, language_id: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT seo_path_info, sw_uuid_hex(language_id) AS language_id
            FROM seo_url
            WHERE path_info = :pathInfo AND is_canonical = 1 AND
                  sales_channel_id = :salesChannelId AND language_id IN (:languageIds)',
            ['pathInfo' => $pathInfo, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId), 'languageIds' => Uuid::fromHexToBytesList($languageIds)],
            ['languageIds' => ArrayParameterType::BINARY, 'salesChannelId' => ParameterType::BINARY]
        );

        return array_map(static fn (array $row): array => ['seoPathInfo' => $row['seo_path_info'], 'languageId' => $row['language_id']], $result);
    }
}
