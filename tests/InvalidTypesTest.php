<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\InvalidTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;

class InvalidTypesTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(LoggerInterface $logger): ExtensionInterface
    {
        return new class(
            $logger,
            [InvalidTypes::class]
        ) extends Extension {};
    }

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
            
            ["\\\\Exception", true], // class
            ["\\\\Iterator", true], // interface
            ["\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Enom", true],
            ["\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Traet", true],
            ["\\\\Twig\\\\Token", true],
            ["Exception", true], // leading \ is optional
            ["Twig\\\\Token", true],
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
            ['iterable<string, iterable<number[]>>', true],
            ['iterable<string, iterable<string, number[]>>', true],
            ['iterable<string, iterable<string, \\\\Exception[]>>', true],
            ['iterable<string, iterable<string, iterable<\\\\Exception>>>', true],
            ['iterable<string, iterable<string, ?\\\\Exception[]>>', true],

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
