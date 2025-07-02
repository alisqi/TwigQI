<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Assertion;

final class AssertType
{
    public static function matches($value, string $type): bool
    {
        // convert iterable short form to canonical form (e.g., `string[]` => `iterable<string>`)
        if (str_ends_with($type, '[]')) {
            $type = 'iterable<' . substr($type, 0, -2) . '>';
        }

        // assert iterables with key and/or value type recursively
        if (str_starts_with($type, 'iterable<')) {
            $matches = [];
            preg_match('/<((string|number),\s*)?(.+)>/', substr($type, 8), $matches);
            [, , $keyType, $valueType] = $matches;
            return self::iterableMatches($value, $valueType, $keyType ?: null);
        }

        // assert class, interface, trait and enum names
        if (str_starts_with($type, '\\')) { // must be FQN
            if (trait_exists($type)) {
                return in_array(
                    substr($type, 1), // strip leading slash (even though trait_exists works with leading slash - WHY?)
                    class_uses($value),
                    true
                );
            }
            return $value instanceof $type;
        }

        return match ($type) {
            'string' => is_string($value) || (is_object($value) && method_exists($value, '__toString')),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'iterable' => is_iterable($value),
            'object' => is_object($value),
            default => true,
        };
    }

    private static function iterableMatches($iterable, string $valueType, ?string $keyType = null): bool
    {
        if (!is_iterable($iterable)) {
            return false;
        }

        foreach ($iterable as $key => $value) {
            if (!self::matches($value, $valueType)) {
                return false;
            }

            if ($keyType !== null && !self::matches($key, $keyType)) {
                return false;
            }
        }

        return true;
    }
}
