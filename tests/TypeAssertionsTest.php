<?php

namespace AlisQI\TwigQI\Tests;

class TypeAssertionsTest extends AbstractTestCase
{
    public static function getNullableTypes(): array
    {
        return [
            ['string', 'hello', true],
            ['?string', 'hello', true],
            ['string', '', true],
            ['?string', '', true],
            ['string', '0', true],
            ['?string', '0', true],
            ['string', null, false],
            ['?string', null, true],

            ['number', 1337, true],
            ['?number', 1337, true],
            ['number', 0, true],
            ['?number', 0, true],
            ['number', null, false],
            ['?number', null, true],
        ];
    }

    /** @dataProvider getNullableTypes */
    public function test_nullableType(string $type, $value, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}")
            ->render(['foo' => $value]);

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
}
