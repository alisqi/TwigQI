<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

class InvalidConstantTest extends AbstractTestCase
{
    public static function getConstants(): array
    {
        return [
            ['DATE_W3C', true],
            ['DateTime', false],
            ['DateTime::W3C', true],
            ['DateTime::NOPE', false],
            ['Twig\\\\Token::ARROW_TYPE', true],
            ['Twig\\\\Token::NOPE', false],
        ];
    }

    #[DataProvider('getConstants')]
    public function test_itValidatesConstants(string $constant, bool $isValid): void
    {
        $this->env->createTemplate("{{ constant('$constant') }}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
    
    #[DataProvider('getConstants')]
    public function test_itValidatesConstantTest(string $constant, bool $isValid): void
    {
        $this->env->createTemplate("{{ 'this' is constant('$constant') }}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    public static function getInvalidConstants(): array
    {
        return [
            ['null'],
            ['1337'],
            ['true'],
            ['[]'],
            ['{}'],
            ['"co" ~ "nst"'],
            ['true ? "true" : "false"'],
        ];
    }

    #[DataProvider('getInvalidConstants')]
    public function test_itErrorsOnNonStringArguments(string $constant): void
    {
        $this->env->createTemplate("{{ constant($constant) }}");

        self::assertNotEmpty($this->errors);
    }

    public function test_itDetectsIsDefined(): void
    {
        $this->env->createTemplate("{{ constant('NOPE') is defined }}");

        self::assertEmpty($this->errors, 'no error expected when is defined is used');
    }

    public static function getInvalidCalls(): array
    {
        return [
            ['constant(true, _self)', 'first argument must be string'],
            ['constant(1337, _self)', 'first argument must be string'],
            ['constant(_self, _self)', 'first argument must be string'],
            ['constant("const", true)', 'second argument must be a variable name'],
            ['constant("const", {})', 'second argument must be a variable name'],
            ['constant("const", _self, true)', 'too many arguments'],
        ];
    }

    #[DataProvider('getInvalidCalls')]
    public function test_itErrorsOnInvalidTwoArgumentCalls(string $call, string $expectedError): void
    {
        $this->env->createTemplate("{{ $call }}");

        self::assertNotEmpty($this->errors);
        self::assertStringContainsString($expectedError, $this->errors[0]);
    }
}
