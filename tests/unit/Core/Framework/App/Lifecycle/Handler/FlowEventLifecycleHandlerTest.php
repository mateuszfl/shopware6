<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Flow\Event\Xml\CustomEvents;
use Shopware\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FlowEventLifecycleHandler::class)]
class FlowEventLifecycleHandlerTest extends TestCase
{
    public function testUpdateEventsReplacesStaleDeclaration(): void
    {
        $appId = Uuid::randomHex();
        $staleId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['swag.before.open_the_doors' => $staleId]);
        $repository = StaticEntityRepository::of(AppFlowEventCollection::class);
        $handler = new FlowEventLifecycleHandler($repository, $connection);
        $handler->updateEvents($this->event('new.event'), $appId, Context::createDefaultContext(), 'en-GB');
        static::assertSame([[['appId' => $appId, 'name' => 'new.event', 'aware' => []]]], $repository->upserts);
        static::assertSame([[['id' => $staleId]]], $repository->deletes);
    }

    public function testUpdateEventsKeepsIdentityOfExistingDeclaration(): void
    {
        $appId = Uuid::randomHex();
        $eventId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['existing.event' => $eventId]);
        $repository = StaticEntityRepository::of(AppFlowEventCollection::class);
        $handler = new FlowEventLifecycleHandler($repository, $connection);
        $handler->updateEvents($this->event('existing.event'), $appId, Context::createDefaultContext(), 'en-GB');
        static::assertSame([[['appId' => $appId, 'name' => 'existing.event', 'aware' => [], 'id' => $eventId]]], $repository->upserts);
        static::assertSame([], $repository->deletes);
    }

    private function event(string $name): Event
    {
        $document = new \DOMDocument();
        $root = $document->createElement('root');
        $event = $document->createElement('flow-event');
        $event->appendChild($document->createElement('name', $name));
        $root->appendChild($event);
        $definition = static::createStub(Event::class);
        $definition->method('getCustomEvents')->willReturn(CustomEvents::fromXml($root));

        return $definition;
    }
}
