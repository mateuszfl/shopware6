<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Cms\Subscriber\UnusedMediaSubscriberTest
 */
#[Package('discovery')]
class UnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        if ($event->getUnusedIds() === []) {
            return;
        }

        $join = ' INNER JOIN cms_slot ON cms_slot_translation.cms_slot_id = cms_slot.id AND cms_slot_translation.cms_slot_version_id = cms_slot.version_id';
        $event->markAsUsed($this->findMediaIds('cms_slot_translation', 'config', JsonPath::parse('.sliderItems.value[*].mediaId'), $event, $join . ' WHERE cms_slot.type IN (\'image-slider\', \'image-gallery\')'));
        $event->markAsUsed($this->findMediaIds('cms_slot_translation', 'config', JsonPath::members(['media', 'value']), $event, $join . ' WHERE cms_slot.type = \'image\''));

        foreach (['category_translation', 'product_translation', 'landing_page_translation'] as $table) {
            $event->markAsUsed($this->findMediaIds($table, 'slot_config', JsonPath::parse('.*.sliderItems.value[*].mediaId'), $event));
            $event->markAsUsed($this->findMediaIds($table, 'slot_config', JsonPath::parse('.*.media.value'), $event));
        }
    }

    /**
     * @return list<string>
     */
    private function findMediaIds(string $table, string $column, JsonPath $path, UnusedMediaSearchEvent $event, string $condition = ''): array
    {
        if ($event->getUnusedIds() === []) {
            return [];
        }

        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $value = $dialect->jsonExtractPath($dialect->quoteQualifiedIdentifier([$table, $column]), $path)->named();
        $sql = 'SELECT ' . $value->sql . ' FROM ' . $dialect->quoteIdentifier($table) . $condition
            . ($condition === '' ? ' WHERE ' : ' AND ') . $dialect->jsonOverlaps($value->sql, ':mediaIds');
        $rows = $this->connection->fetchFirstColumn(
            $sql,
            [...$value->parameters, 'mediaIds' => json_encode($event->getUnusedIds(), \JSON_THROW_ON_ERROR)],
            [...$value->types, 'mediaIds' => ParameterType::STRING],
        );

        $ids = [];
        foreach ($rows as $row) {
            $value = json_decode($row, true, flags: \JSON_THROW_ON_ERROR);
            foreach (\is_array($value) ? $value : [$value] as $id) {
                if (\is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_intersect($event->getUnusedIds(), $ids));
    }
}
