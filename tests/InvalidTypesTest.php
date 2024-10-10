<?php

namespace AlisQI\TwigQI\Tests;

class InvalidTypesTest extends AbstractTestCase
{
    public static function getTypes(): array
    {
        return [
            ["{}", true],
            ["{foo: 'string'}", true],
            ["{foo: 'number'}", true],
            ["{foo: 'boolean'}", true],
            ["{foo: 'null'}", true],
            ["{foo: 'iterable'}", true],
            ["{foo: 'object'}", true],
            
            ["{foo: 'bar'}", false],
            ["{foo: '[]'}", false],
            ["{foo: '{}'}", false],
            ["{foo: 'any'}", false],
            ["{foo: 'mixed'}", false],
            ["{foo: 'resource'}", false],
        ];
    }

    /** @dataProvider getTypes */
    public function test_itValidatesTypes(string $types, bool $isValid): void
    {
        $this->env->createTemplate("{% types $types %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

}
