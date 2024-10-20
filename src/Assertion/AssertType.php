<?php

namespace AlisQI\TwigQI\Assertion;

final class AssertType
{
    public static function matches($value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'iterable' => is_iterable($value),
            'object' => is_object($value),
            default => true,
        };
    }
}
