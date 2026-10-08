<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriberTest
 */
#[Package('discovery')]
class CustomFieldsUnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
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

        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        $mediaType = $dialect->jsonExtractPath('f.config', JsonPath::members(['customFieldType']))->named();
        $entity = $dialect->jsonExtractPath('f.config', JsonPath::members(['entity']))->named();
        $component = $dialect->jsonExtractPath('f.config', JsonPath::members(['componentName']))->named();
        /** @var list<array{name: string, entity_name: string}> $fields */
        $fields = $this->connection->fetchAllAssociative(
            'SELECT f.name, fsr.entity_name FROM custom_field f '
            . 'INNER JOIN custom_field_set fs ON f.set_id = fs.id '
            . 'INNER JOIN custom_field_set_relation fsr ON fs.id = fsr.set_id '
            . 'WHERE ' . $dialect->jsonScalar($mediaType->sql) . ' = \'media\' OR (f.type = \'select\' AND '
            . $dialect->jsonScalar($entity->sql) . ' = \'media\' AND '
            . $dialect->jsonScalar($component->sql) . ' IN (\'sw-entity-single-select\', \'sw-entity-multi-id-select\'))',
            [...$mediaType->parameters, ...$entity->parameters, ...$component->parameters],
            [...$mediaType->types, ...$entity->types, ...$component->types],
        );

        foreach ($fields as $field) {
            if ($event->getUnusedIds() === []) {
                return;
            }

            $table = $dialect->quoteIdentifier($this->getTableName($field['entity_name']));
            $value = $dialect->jsonExtractPath($table . '.custom_fields', JsonPath::members([$field['name']]))->named();
            $rows = $this->connection->fetchFirstColumn(
                'SELECT ' . $value->sql . ' FROM ' . $table . ' WHERE ' . $dialect->jsonOverlaps($value->sql, ':mediaIds'),
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
            $event->markAsUsed($ids);
        }
    }

    private function getTableName(string $entity): string
    {
        $definition = $this->definitionRegistry->getByEntityName($entity);

        $customFields = $definition->getField('customFields');

        $table = $definition->getEntityName();

        if ($customFields instanceof TranslatedField) {
            $table = $definition->getTranslationDefinition()?->getEntityName() ?? $table;
        }

        return $table;
    }
}
