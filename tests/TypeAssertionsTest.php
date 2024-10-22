<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use ArrayIterator;
use Exception;
use Twig\Node\Node;

class TypeAssertionsTest extends AbstractTestCase
{
    public static function getOptionalVariables(): array
    {
        return [
            ['foo', ['foo' => 'bar'], true],
            ['foo', ['foo' => ''], true],

            ['foo', ['oof' => 'bar'], false],
            ['foo?', ['oof' => 'bar'], true],
        ];
    }

    /** @dataProvider getOptionalVariables */
    public function test_optionalVariable(string $variable, array $context, bool $isValid): void
    {
        $this->env->createTemplate("{% types {{$variable}: 'string'} %}")
            ->render($context);

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

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

    public static function getTypes(): array
    {
        return [
            ['string', 'hello', true],
            ['string', '', true],
            ['string', '0', true],

            ['string', true, false],
            ['string', 1, false],
            ['string', [], false],
            ['string', new Exception(), false],

            ['number', 0, true],
            ['number', 1, true],
            ['number', 0.0, true],
            ['number', 1.0, true],

            ['number', '', false],
            ['number', '0', false],
            ['number', [], false],
            ['number', new Exception(), false],

            ['boolean', true, true],
            ['boolean', false, true],

            ['boolean', '', false],
            ['boolean', 'true', false],
            ['boolean', '0', false],
            ['boolean', [], false],
            ['boolean', new Exception(), false],

            ['object', new Exception(), true],

            ['object', 'object', false],
            ['object', true, false],
            ['object', [], false],

            ['iterable', [], true],
            ['iterable', [13, 37], true],
            ['iterable', new ArrayIterator([13, 37]), true],
            ['iterable', ['foo' => 'bar'], true],
            ['iterable', 'hello', false],

            ['iterable<string>', [], true],
            ['iterable<string>', ['hello'], true],
            ['iterable<string>', new ArrayIterator(['hello']), true],
            ['iterable<string>', [1337], false],
            ['iterable<string>', 'hello', false],

            ['string[]', [], true],
            ['string[]', ['foo'], true],
            ['string[]', 'foo', false],
            ['string[]', [1337], false],

            ['iterable<string, string>', [], true],
            ['iterable<string, string>', ['foo' => 'bar'], true],
            ['iterable<string, string>', new ArrayIterator(['foo' => 'bar']), true],
            ['iterable<string, string>', ['foo' => 1337], false],
            ['iterable<string, string>', ['foo' => ['bar']], false],
            ['iterable<string, string>', [13, 37], false],

            ['iterable<number, number>', [], true],
            ['iterable<number, number>', [13, 37], true],
            ['iterable<number, number>', [13 => 37], true],
            ['iterable<number, number>', new ArrayIterator([13 => 37]), true],
            ['iterable<number, number>', ['13' => 37], true],
            ['iterable<number, number>', ['leet' => 1337], false],

            ['iterable<string, iterable<string>>', ['foo' => ['bar']], true],
            ['iterable<string, iterable<number>>', ['foo' => [13, 37]], true],
            ['iterable<string, iterable<string>>', ['foo' => [13, 37]], false],

            ['iterable<iterable<iterable<string, number>>>', [[[]]], true],
            ['iterable<iterable<iterable<string, number>>>', [[['foo' => 1337]]], true],
            ['iterable<iterable<iterable<string, number>>>', [[[13, 37]]], false],
            ['iterable<iterable<iterable<string, number>>>', [[['foo' => 'bar']]], false],

            ['\\\\Exception', new Exception(), true],
            ['\\\\Exception', new Node(), false],
            ['iterable<string, \\\\Twig\\\\Node\\\\Node>', ['node' => new Node()], true],
            ['iterable<string, \\\\Twig\\\\Node\\\\Node[]>', ['nodes' => [new Node(), new Node()]], true],
            ['iterable<string, \\\\Twig\\\\Node\\\\Node>', ['node' => new Exception()], false],

            ['\\\\Traversable', new Node(), true],
            ['\\\\Traversable', true, false],
        ];
    }

    /** @dataProvider getTypes */
    public function test_typeAssertion(string $type, $value, $isValid): void
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
