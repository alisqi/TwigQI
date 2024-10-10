<?php

namespace AlisQI\TwigQI\Tests;

class InvalidTypesTest extends AbstractTestCase
{
    public static function getTypes(): array
    {
        return [
            ["string", true],
            ["number", true],
            ["boolean", true],
            ["null", true],
            ["iterable", true],
            ["object", true],
            
            ["bar", false],
            ["[]", false],
            ["{}", false],
            ["any", false],
            ["mixed", false],
            ["resource", false],
            
            ["\\\\Exception", true],
            ["\\\\App\\\\Exception", true],
            ["Exception", false],
            ["\\\\Inv alid", false],
            ["\\\\Inv-alid", false],
            ["\\\\App\\\\", false],
        ];
    }

    /** @dataProvider getTypes */
    public function test_itValidatesTypes(string $type, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

}
