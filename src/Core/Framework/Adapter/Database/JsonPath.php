<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Shopware\Core\Framework\Log\Package;

/**
 * A DAL path is data, never SQL or an arbitrary SQL/JSON predicate.
 *
 * @internal
 */
#[Package('framework')]
final class JsonPath
{
    /**
     * @param list<array{kind: string, value: string}> $steps
     */
    private function __construct(public readonly array $steps)
    {
    }

    public static function parse(string $path): self
    {
        $steps = [];
        $offset = 0;
        while ($offset < \strlen($path)) {
            if (preg_match('/\G\[([0-9]+|\*)\]/', $path, $match, 0, $offset)) {
                $steps[] = ['kind' => 'index', 'value' => $match[1]];
            } elseif (preg_match('/\G\.((?:"(?:[^"\\\\]|\\\\.)*")|[^.\[\]"]+)/us', $path, $match, 0, $offset)) {
                $quoted = str_starts_with($match[1], '"');
                $value = $quoted ? json_decode($match[1], true, flags: \JSON_THROW_ON_ERROR) : $match[1];
                \assert(\is_string($value));
                $steps[] = ['kind' => !$quoted && $value === '*' ? 'wildcard' : 'member', 'value' => $value];
            } else {
                throw DatabaseDialectException::invalidOperation('invalid JSON accessor path');
            }
            $offset += \strlen($match[0]);
        }

        return new self($steps);
    }

    /**
     * @param list<string> $members
     */
    public static function members(array $members): self
    {
        return new self(array_map(static fn (string $member): array => ['kind' => 'member', 'value' => $member], $members));
    }

    public function expression(): string
    {
        $path = '$';
        foreach ($this->steps as $step) {
            $path .= match ($step['kind']) {
                'index' => '[' . $step['value'] . ']',
                'wildcard' => '.*',
                default => '.' . json_encode($step['value'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            };
        }

        return $path;
    }

    public function hasWildcard(): bool
    {
        foreach ($this->steps as $step) {
            if ($step['kind'] === 'wildcard' || ($step['kind'] === 'index' && $step['value'] === '*')) {
                return true;
            }
        }

        return false;
    }
}
