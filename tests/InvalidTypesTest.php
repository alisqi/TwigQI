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
            ["\\\\Twig\\\\Token", true],
            ["Exception", false],
            ["\\\\Foo", false],
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

    public static function getDeprecatedTypes(): array
    {
        return [
          ['bool'],  
          ['int'],  
          ['float'],  
        ];
    }
    
    /** @dataProvider getDeprecatedTypes */
    public function test_deprecatedTypes(string $type): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertStringContainsString(
            "Deprecated type '$type' used",
            implode(', ', $this->errors)
        );
    }

    public static function getNullableTypes(): array
    {
        return [
            ['?boolean', true],
            ['?iterable', true],
            ['?object', true],
            ['?\\\\Exception', true],

            ['??boolean', false],
            ['!boolean', false],
            ['boolean|null', false],
            ['boolean?', false],
        ];
    }

    /** @dataProvider getNullableTypes */
    public function test_nullableShorthand(string $type, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    public static function getArrayTypes(): array
    {
        return [
            ['number[]', true],
            ['?number[]', true],
            ['object[]', true],
            ['iterable[]', true],

            ['number[][]', false],
            ['[]number', false],
            ['[number]', false],
            ['[][]', false],
        ];
    }

    /** @dataProvider getArrayTypes */
    public function test_arrayTypes(string $type, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
}
