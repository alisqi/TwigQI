<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

class InvalidDotOperationTest extends AbstractTestCase
{
    public function test_itIgnoresAttributesOnExpressions(): void
    {
        $this->env->createTemplate(<<<EOF
            {{ ([]|first).bad }}
            {{ (this ?: that).bad }}
        EOF);

        self::assertEmpty(
            $this->errors,
            implode(', ', $this->errors)
        );
    }

    public function test_itIgnoresUndeclaredVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% types {foo: 'string'} %}
            {{ bar.attr }}
            {{ baz.attr }}
        EOF);

        self::assertEmpty(
            $this->errors,
            implode(', ', $this->errors)
        );
    }

    public static function getInvalidTypesForDotOperator(): array
    {
        return [
            ['string'],
            ['number'],
            ['boolean'],
            ['?string'],
            ['?number'],
            ['?boolean'],
        ];
    }

    #[DataProvider('getInvalidTypesForDotOperator')]
    public function test_itDetectsDotOperatorOnUnsupportedTypes(string $type): void
    {
        $this->env->createTemplate(<<<EOF
            {% types {foo: '$type'} %}
            {{ foo.attr }}
        EOF);

        self::assertNotEmpty(
            $this->errors,
            "Error should trigger when using dot operator for type '$type'"
        );
    }

    public function test_itSupportsTemplateScope(): void
    {
        $this->env->createTemplate("{% types {foo: 'string'} %}");
        $this->env->createTemplate("{{ foo.bad }}"); // this is a separate template: types must not carry over!

        self::assertEmpty(
            $this->errors,
            implode(', ', $this->errors)
        );
    }

    public static function getTemplatesWithMacros(): array
    {
        return [
            // Data set #0
            // type declaration _inside_ macro shouldn't affect _outer_ scope
            [
                "
                {% types {foo: '\\\\DateTime'} %}

                {% macro marco(foo) %}
                    {% types {foo: '\\\\Exception'} %}
                {% endmacro %}

                {{ foo.timezone }}
                "
            ],

            // Data set #1
            // type declaration _outside_ macro shouldn't affect _inner_ scope
            [
                "
                {% macro marco(foo) %}
                    {% types {foo: '\\\\DateTime'} %}
                    {{ foo.timezone }}
                {% endmacro %}

                {% types {foo: '\\\\Exception'} %}
                "
            ],

            // Data set #2
            // same as #1, but outer type declaration comes _before_ macro
            [
                "
                {% types {foo: '\\\\Exception'} %}

                {% macro marco(foo) %}
                    {% types {foo: '\\\\DateTime'} %}
                    {{ foo.timezone }}
                {% endmacro %}
                "
            ],

            // Data set #3
            // same as #2, but macro variable is untyped
            [
                "
                {% types {foo: '\\\\Exception'} %}

                {% macro marco(foo) %}
                    {{ foo.bad }}
                {% endmacro %}
                "
            ],

            // Data set #3
            // same as #0, but global variable is untyped
            [
                "
                {% macro marco(foo) %}
                    {% types {foo: '\\\\Exception'} %}
                {% endmacro %}

                {{ foo.bad }}
                "
            ],

            // Data set #5
            // same as #0, but outer type declaration comes before
            [
                "
                {% types {foo: '\\\\DateTime'} %}

                {% macro marco(foo) %}
                    {% types {foo: '\\\\Exception'} %}
                    {{ foo.code }}
                {% endmacro %}
                "
            ],

            // Data set #6
            // multiple macros, 2nd macro's variable is untyped
            [
                "
                {% macro marco(foo) %}
                    {% types {foo: '\\\\Exception'} %}
                    {{ foo.code }}
                {% endmacro %}

                {% macro polo(foo) %}
                    {{ foo.bad }}
                {% endmacro %}
                "
            ],

            // Data set #7
            // same as #6, but in reverse
            [
                "
                {% macro marco(foo) %}
                    {{ foo.bad }}
                {% endmacro %}
                
                {% macro polo(foo) %}
                    {% types {foo: '\\\\Exception'} %}
                    {{ foo.code }}
                {% endmacro %}
                "
            ],
        ];
    }

    #[DataProvider('getTemplatesWithMacros')]
    public function test_isSupportsMacroScope(string $template): void
    {
        $this->env->createTemplate($template);

        self::assertEmpty(
            $this->errors,
            implode(', ', $this->errors)
        );
    }

    public static function getClassNamesAndAttributes(): array
    {
        $dummyClassFqn = '\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Dummy';

        return [
            [$dummyClassFqn, 'pubProp', true],
            [$dummyClassFqn, 'pubMeth', true],
            [$dummyClassFqn, 'protProp', false],
            [$dummyClassFqn, 'protMeth', false],
            [$dummyClassFqn, 'privProp', false],
            [$dummyClassFqn, 'privMeth', false],
            [$dummyClassFqn, 'invalid', false],
        ];
    }

    #[DataProvider('getClassNamesAndAttributes')]
    public function test_itValidatesDotOperationOnObjects(string $type, string $attribute, bool $isValid): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% types {foo: '$type'} %}
            {{ foo.$attribute }}
        EOF
        );

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    #[DataProvider('getClassNamesAndAttributes')]
    public function test_itValidatesDotOperationOnObjectsEvenIfNullable(string $type, string $attribute, bool $isValid): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% types {foo: '?$type'} %}
            {{ foo.$attribute }}
        EOF
        );

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    public static function getAttributeShorthands(): array
    {
        $dummyClassFqn = '\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Dummy';

        return [
            [$dummyClassFqn, 'git'], // getGit()
            [$dummyClassFqn, 'iz'],  // isIz()
            [$dummyClassFqn, 'haz'], // hasHaz()
        ];
    }

    #[DataProvider('getAttributeShorthands')]
    public function test_itSupportsAttributeShorthand(string $type, string $attribute): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% types {foo: '$type'} %}
            {{ foo.$attribute }}
        EOF
        );

        self::assertEmpty(
            $this->errors,
            "Error should not trigger when using attribute shorthand for type '$type' and attribute '$attribute'"
        );
    }

    public function test_itSupportsDynamicObjectProperties(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% types {foo: '\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Dummy'} %}
            {{ foo.dynProp }}
        EOF
        );

        self::assertEmpty(
            $this->errors,
            "Error should not trigger when accessing dynamic object property"
        );
    }

    public static function getTwigContentWithVariableOverloads(): array
    {
        return [
            ['{{ []|filter((foo, k) => foo.bar) }}', true],
            ['{{ []|filter((v, foo) => foo.bar) }}', true],
            ['{% for k, foo in [] %}{{ foo.bar }}{% endfor %}', true],
            ['{% for foo, v in [] %}{{ foo.bar }}{% endfor %}', true],
            ['{% set foo = 1 %}{{ foo.bar }}', true],
            ['{% set foo, bar = 1, 1 %}{{ foo.bar }}', true],

            ['{{ []|filter((foo) => foo.bar) }} {{ foo.baz }}', false],
            ['{% for foo in [] %}{{ foo.bar }}{% endfor %} {{ foo.baz }}', false],
        ];
    }

    #[DataProvider('getTwigContentWithVariableOverloads')]
    public function test_itSupportsNameOverloading(string $content, bool $isValid): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% types {foo: '\\\\Exception'} %}
            $content
        EOF
        );

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }

    public function test_applyTagDoesNotBreak(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% apply upper %}
                {{ foo.bar }}
            {% endapply %}
        EOF
        );

        self::assertEmpty(
            $this->errors,
            "Error should not trigger when accessing dynamic object property"
        );
    }

    public static function getTwigTemplateWithIterableTypes(): array
    {
        return [
            [
                <<<EOF
                {% types {
                    foos: '\\\\Exception[]',
                } %}
                {% for foo in foos %}
                    {{ foo.bad }}
                {% endfor %}
                EOF,
                false,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'iterable<string, \\\\Exception>',
                } %}
                {% for foo in foos %}
                    {{ foo.bad }}
                {% endfor %}
                EOF,
                false,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'iterable<string, iterable<string, \\\\Exception>>',
                } %}
                {% for bars in foos %}
                    {% for baz in bars %}
                        {{ baz.bad }}
                    {% endfor %}
                {% endfor %}
                EOF,
                false,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'iterable<string, string>',
                } %}
                {% for foo in foos %}
                    {{ foo.bad }}
                {% endfor %}
                EOF,
                false,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'iterable<string, string>',
                } %}
                {% for foo, bar in foos %}
                    {{ foo.bad }}
                {% endfor %}
                EOF,
                false,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'mixed[]',
                } %}
                {% for foo in foos %}
                    {{ foo.bad }}
                {% endfor %}
                EOF,
                true,
            ],

            [
                <<<EOF
                {% types {
                    foos: '\\\\Exception[]',
                } %}
                {% for foo in foos %}
                    {{ foo.code }}
                {% endfor %}
                EOF,
                true,
            ],

            [
                <<<EOF
                {% types {
                    foos: 'iterable[]',
                } %}
                {% for foo in foos %}
                    {{ foo.code }}
                {% endfor %}
                EOF,
                true,
            ],
        ];
    }

    #[DataProvider('getTwigTemplateWithIterableTypes')]
    public function test_itImpliesTypeFromIterables($template, bool $isValid): void
    {
        $this->env->createTemplate($template);

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
}
