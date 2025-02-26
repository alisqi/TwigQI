<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

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
            ["mixed", true],
            
            ["bar", false],
            ["[]", false],
            ["{}", false],
            ["any", false],
            ["resource", false],
            
            ["\\\\Exception", true],
            ["\\\\Iterator", true],
            ["\\\\PHPUnit\\\\Framework\\\\MockObject\\\\Method", true],
            ["\\\\Twig\\\\Token", true],
            ["Exception", false],
            ["\\\\Foo", false],
            ["\\\\Inv alid", false],
            ["\\\\Inv-alid", false],
            ["\\\\App\\\\", false],
        ];
    }

    #[DataProvider('getTypes')]
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
    
    #[DataProvider('getDeprecatedTypes')]
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

    #[DataProvider('getNullableTypes')]
    public function test_nullableShorthand(string $type, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    public static function getIterableTypes(): array
    {
        return [
            ['number[]', true],
            ['?number[]', true],
            ['object[]', true],
            ['iterable', true],

            ['number[][]', false],
            ['[]number', false],
            ['[number]', false],
            ['[][]', false],
            
            ['iterable<number>', true],
            ['iterable<number, string>', true],
            ['iterable<number,number>', true],
            ['iterable<string, ?number>', true],
            ['iterable<string, iterable<number>>', true],

            ['iterable<boolean, number>', false],
            ['iterable<iterable, number>', false],
            ['iterable<string, iterable<number>', false],
            ['iterable<string, iterable<whoops>>', false],
            ['iterable{string}', false],
            ['iterable<>', false],
        ];
    }

    #[DataProvider('getIterableTypes')]
    public function test_iterableTypes(string $type, bool $isValid): void
    {
        $this->env->createTemplate("{% types {foo: '$type'} %}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
}
