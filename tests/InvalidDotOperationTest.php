<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

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
        ];
    }

    /** @dataProvider getInvalidTypesForDotOperator */
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
}
