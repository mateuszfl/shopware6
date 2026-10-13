<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
#[Package('fundamentals@discovery')]
class LanguageLoader implements LanguageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return LanguageData
     */
    public function loadLanguages(): array
    {
        $data = $this->connection->createQueryBuilder()
            ->select('sw_uuid_hex(language.id) AS array_key, sw_uuid_hex(language.id) AS id, locale.code, parentLocale.code AS parent_code, sw_uuid_hex(language.parent_id) AS parent_id')
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->leftJoin('language', 'language', 'parentLanguage', 'language.parent_id = parentLanguage.id')
            ->leftJoin('parentLanguage', 'locale', 'parentLocale', 'parentLanguage.translation_code_id = parentLocale.id')
            ->executeQuery()
            ->fetchAllAssociative();

        $data = array_map(static function (array $row): array {
            $row['parentCode'] = $row['parent_code'];
            $row['parentId'] = $row['parent_id'];
            unset($row['parent_code'], $row['parent_id']);

            return $row;
        }, $data);

        /** @var LanguageData $grouped */
        $grouped = FetchModeHelper::groupUnique($data);

        return $grouped;
    }
}
