<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\DatabasePlatformResolver;
use Shopware\Core\Framework\Adapter\Database\JsonPath;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\ContractConnectionFactory;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Contract\_fixtures\DatabaseEndpoint;

/**
 * @internal
 */
#[Package('framework')]
class JsonAccessorContractTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = ContractConnectionFactory::create('DATABASE_CONTRACT_URL');
        $endpoint = DatabaseEndpoint::observe($this->connection);
        static::assertFalse($endpoint->standby);
        static::assertStringStartsWith($endpoint->platform === 'postgresql' ? '18.6 ' : '8.4.', $endpoint->version);
        if ($endpoint->platform === 'postgresql') {
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS sw_json_accessor CASCADE');
            $this->connection->executeStatement('CREATE SCHEMA sw_json_accessor');
            $this->connection->executeStatement('SET search_path TO sw_json_accessor');
            $this->connection->executeStatement('CREATE COLLATION sw_type_ci (provider = icu, locale = \'und-u-ks-level1\', deterministic = false)');
        }
        $this->connection->executeStatement('CREATE TABLE sw_json_value (id INT PRIMARY KEY, payload ' . ($endpoint->platform === 'postgresql' ? 'JSONB' : 'JSON') . ')');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS sw_json_value');
            if ((new DatabasePlatformResolver($this->connection))->capabilities()->supportsReturning) {
                $this->connection->executeStatement('DROP SCHEMA sw_json_accessor CASCADE');
            }
            $this->connection->close();
        }
    }

    /**
     * @return iterable<string, array{?string, string, mixed}>
     */
    public static function extractionCases(): iterable
    {
        yield 'SQL NULL' => [null, '.value', null];
        yield 'missing key' => ['{}', '.value', null];
        yield 'JSON null' => ['{"value":null}', '.value', null];
        yield 'string null' => ['{"value":"null"}', '.value', 'null'];
        yield 'true' => ['{"value":true}', '.value', 'true'];
        yield 'false' => ['{"value":false}', '.value', 'false'];
        yield 'zero' => ['{"value":0}', '.value', '0'];
        yield 'string zero' => ['{"value":"0"}', '.value', '0'];
        yield 'number' => ['{"value":12.5}', '.value', '12.5'];
        yield 'string number' => ['{"value":"12.5"}', '.value', '12.5'];
        yield 'empty object' => ['{"value":{}}', '.value', '{}'];
        yield 'empty array' => ['{"value":[]}', '.value', '[]'];
        yield 'Unicode' => ['{"value":"żółć 🐈"}', '.value', 'żółć 🐈'];
        foreach (['key.with.dot', 'key-with-hyphen', 'key"quote', 'key\\slash', 'key\' OR 1=1 --', ''] as $key) {
            yield 'literal key ' . $key => [json_encode([$key => 'found'], \JSON_THROW_ON_ERROR), '.' . json_encode($key, \JSON_THROW_ON_ERROR), 'found'];
        }
        yield 'array index' => ['{"values":[10,20]}', '.values[1]', '20'];
        yield 'absent array index' => ['{"values":[10]}', '.values[2]', null];
        yield 'array wildcard' => ['{"values":[1,"1",false,null,{}]}', '.values[*]', '[1, "1", false, null, {}]'];
        yield 'object wildcard' => ['{"values":{"a":1,"b":2}}', '.values.*', '[1, 2]'];
        yield 'object wildcard with differing key lengths' => ['{"values":{"long":3,"a":1,"bb":2}}', '.values.*', '[1, 2, 3]'];
        yield 'wildcard skips absent members' => ['{"values":[{"a":1},{},{"a":3}]}', '.values[*].a', '[1, 3]'];
        yield 'index zero autowraps scalar' => ['{"value":7}', '.value[0]', '7'];
        yield 'index zero autowraps object' => ['{"value":{"a":7}}', '.value[0].a', '7'];
        yield 'scalar array wildcard' => ['{"value":7}', '.value[*]', null];
        yield 'wildcard without matches' => ['{"values":[]}', '.values[*]', null];
        yield 'array is not implicitly traversed by member' => ['{"values":[{"a":1}]}', '.values.a', null];
    }

    #[DataProvider('extractionCases')]
    public function testExtractsNestedValuesWithoutInterpolatingPaths(?string $document, string $suffix, mixed $expected): void
    {
        $this->connection->insert('sw_json_value', ['id' => 1, 'payload' => $document]);
        $builder = new JsonFieldAccessorBuilder($this->connection);
        $field = new JsonField('payload', 'payload');
        $fragment = $builder->buildAccessorFragment('sw_json_value', $field, Context::createDefaultContext(), 'payload' . $suffix);
        static::assertNotNull($fragment);
        static::assertNotEmpty($fragment->parameters);
        static::assertStringNotContainsString(JsonPath::parse($suffix)->expression(), $fragment->sql);
        $actual = $this->connection->fetchOne('SELECT ' . $fragment->sql . ' FROM sw_json_value', $fragment->parameters, $fragment->types);
        static::assertSame($expected, $actual);
        // The retained string API must safely execute the same path, including hostile keys.
        static::assertSame($expected, $this->connection->fetchOne('SELECT ' . $builder->buildAccessor('sw_json_value', $field, Context::createDefaultContext(), 'payload' . $suffix) . ' FROM sw_json_value'));
    }

    public function testRawExtractionDistinguishesMissingJsonNullAndSqlNullAndPreservesScalarTypes(): void
    {
        $dialect = (new DatabasePlatformResolver($this->connection))->dialect();
        foreach ([null, '{}', '{"value":null}', '{"value":"null"}', '{"value":true}', '{"value":0}', '{"value":"0"}', '{"value":{}}', '{"value":[]}'] as $index => $document) {
            $this->connection->insert('sw_json_value', ['id' => $index, 'payload' => $document]);
        }
        $fragment = $dialect->jsonExtractPath('payload', JsonPath::members(['value']));
        $rows = $this->connection->fetchFirstColumn('SELECT ' . $fragment->sql . ' FROM sw_json_value ORDER BY id', $fragment->parameters, $fragment->types);
        static::assertSame([null, null, 'null', '"null"', 'true', '0', '"0"', '{}', '[]'], $rows);
    }

    /**
     * @return iterable<string, array{mixed, float, int}>
     */
    public static function typedScalars(): iterable
    {
        yield 'boolean true' => [true, 0.0, 1];
        yield 'boolean false' => [false, 0.0, 0];
        yield 'numeric zero' => [0, 0.0, 0];
        yield 'string zero' => ['0', 0.0, 0];
        yield 'numeric decimal' => [12.5, 12.5, 1];
        yield 'string decimal' => ['12.5', 12.5, 1];
        yield 'true string' => ['true', 0.0, 1];
        yield 'false string' => ['false', 0.0, 0];
        yield 'null string' => ['null', 0.0, 0];
        yield 'empty array' => [[], 0.0, 0];
        yield 'empty object' => [new \stdClass(), 0.0, 0];
        yield 'Unicode' => ['żółć', 0.0, 0];
        yield 'leading numeric prefix' => [' -12.5e1tail', -125.0, 1];
    }

    #[DataProvider('typedScalars')]
    public function testTypedNumberAndBooleanConversionsMatchReference(mixed $value, float $number, int $flag): void
    {
        $this->connection->insert('sw_json_value', ['id' => 1, 'payload' => json_encode(['number' => $value, 'flag' => $value], \JSON_THROW_ON_ERROR)]);
        $builder = new JsonFieldAccessorBuilder($this->connection);
        $field = new JsonField('payload', 'payload', [new FloatField('number', 'number'), new BoolField('flag', 'flag')]);
        foreach (['number' => $number, 'flag' => (float) $flag] as $key => $expected) {
            $fragment = $builder->buildAccessorFragment('sw_json_value', $field, Context::createDefaultContext(), 'payload.' . $key);
            static::assertNotNull($fragment);
            static::assertSame($expected, (float) $this->connection->fetchOne('SELECT ' . $fragment->sql . ' FROM sw_json_value', $fragment->parameters, $fragment->types));
        }
    }

    public function testInvalidDateTextAndJsonNullProduceSqlNull(): void
    {
        $builder = new JsonFieldAccessorBuilder($this->connection);
        $field = new JsonField('payload', 'payload', [new DateField('day', 'day'), new DateTimeField('time', 'time')]);
        foreach (['{}', '{"day":null,"time":null}', '{"day":false,"time":true}', '{"day":0,"time":"0"}', '{"day":"infinity","time":"now"}', '{"day":"null","time":"garbage"}'] as $document) {
            $this->connection->executeStatement('DELETE FROM sw_json_value');
            $this->connection->insert('sw_json_value', ['id' => 1, 'payload' => $document]);
            foreach (['day', 'time'] as $key) {
                $fragment = $builder->buildAccessorFragment('sw_json_value', $field, Context::createDefaultContext(), 'payload.' . $key);
                static::assertNotNull($fragment);
                static::assertNull($this->connection->fetchOne('SELECT ' . $fragment->sql . ' FROM sw_json_value', $fragment->parameters, $fragment->types));
            }
        }
    }

    public function testMappedScalarsRetainNumericBooleanAndDateComparisonSemantics(): void
    {
        $builder = new JsonFieldAccessorBuilder($this->connection);
        $field = new JsonField('payload', 'payload', [new FloatField('number', 'number'), new BoolField('flag', 'flag'), new DateTimeField('time', 'time'), new DateField('day', 'day')]);
        $this->connection->insert('sw_json_value', ['id' => 1, 'payload' => '{"number":" -12.5e1tail","flag":"true","time":"2026-10-03 10:11:12.123","day":"2026-10-03"}']);
        foreach (['number' => -125.0, 'flag' => 1.0] as $key => $expected) {
            $fragment = $builder->buildAccessorFragment('sw_json_value', $field, Context::createDefaultContext(), 'payload.' . $key);
            static::assertNotNull($fragment);
            static::assertSame($expected, (float) $this->connection->fetchOne('SELECT ' . $fragment->sql . ' FROM sw_json_value', $fragment->parameters, $fragment->types));
        }
        foreach (['time' => '2026-10-03 10:11:12.123', 'day' => '2026-10-03'] as $key => $expected) {
            $fragment = $builder->buildAccessorFragment('sw_json_value', $field, Context::createDefaultContext(), 'payload.' . $key);
            static::assertNotNull($fragment);
            static::assertSame($expected, $this->connection->fetchOne('SELECT ' . $fragment->sql . ' FROM sw_json_value', $fragment->parameters, $fragment->types));
        }
    }
}
