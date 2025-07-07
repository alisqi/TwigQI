<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\BadArgumentCountInMacroCall;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

class BadArgumentCountInMacroCallTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(LoggerInterface $logger): ExtensionInterface
    {
        return new class(
            $logger,
            [BadArgumentCountInMacroCall::class]
        ) extends Extension {
        };
    }

    public function test_itDoesNotWarnForMatchingArgumentNumber(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% macro marco() %}{% endmacro %}
            {% macro polo(arg, gra) %}{% endmacro %}
            {{ _self.marco() }}
            {{ _self.polo(13, 37) }}
        EOF
        );

        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public function test_itWarnsForTooManyArguments(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% macro marco() %}{% endmacro %}
            {{ _self.marco(1337) }}
        EOF
        );

        self::assertCount(1, $this->errors);

        self::assertStringContainsString('marco', $this->errors[0]);
        self::assertStringContainsStringIgnoringCase('too many', $this->errors[0]);
    }

    public function test_itSupportsVarArgs(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% macro marco() %}
                {{ varargs|length }}
            {% endmacro %}
            {{ _self.marco() }}
            {{ _self.marco(1337) }}
            {{ _self.marco(13, 37) }}
        EOF
        );

        self::assertEmpty($this->errors, implode(', ', $this->errors));

        $this->env->createTemplate(
            <<<EOF
            {% macro marco2(polo) %}
                {% if polo %}
                    {{ varargs|length }}
                {% endif %}
            {% endmacro %}
            {{ _self.marco2(13, 37) }}
        EOF
        );

        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public function test_itSupportsArrayDefaults(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% macro marco(polo = []) %}
                {{ polo|length }}
            {% endmacro %}
            {{ _self.marco() }}
        EOF
        );

        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public static function getTooFewArgumentsTestCases(): array
    {
        return [
            [
                '{% macro marco(polo = true) %}{% endmacro %} {{ _self.marco() }}',
                []
            ],
            [
                '{% macro marco(polo = null) %}{% endmacro %} {{ _self.marco() }}',
                []
            ],
            [
                '{% macro marco(po, lo = true) %}{% endmacro %}{{ _self.marco(1337) }}',
                []
            ],
            [
                '{% macro marco(polo) %}{% endmacro %} {{ _self.marco() }}',
                ['Too few arguments (0)']
            ],
            [
                '{% macro marco(po, lo) %}{% endmacro %} {{ _self.marco() }}',
                ['Too few arguments (0)']
            ],
            [
                '{% macro marco(po, lo) %}{% endmacro %} {{ _self.marco(1337) }}',
                ['Too few arguments (1)']
            ],
        ];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('getTooFewArgumentsTestCases')]
    public function test_itWarnsForTooFewArguments(string $template, array $errors): void
    {
        $this->env->createTemplate($template);

        self::assertCount(count($errors), $this->errors);

        foreach ($errors as $i => $error) {
            self::assertStringContainsString($error, $this->errors[$i]);
        }
    }

    public static function getImportedMacroTests(): array
    {
        return [
            // from ... import
            ['{% from "marco.twig" import marco %} {{ marco(1) }}', true],
            ['{% from "marco.twig" import marco %} {{ marco() }}', false],

            // from ... import with alias
            ['{% from "marco.twig" import marco as polo %} {{ polo() }}', false],
            ['{% from "marco.twig" import marco as polo %} {{ polo(1) }}', true],

            [
                '{% from "marco.twig" import marco as polo %} {% macro marco(polo) %}{% endmacro %} {{ _self.marco() }}',
                false
            ],
            [
                '{% from "marco.twig" import marco as polo %} {% macro marco(polo) %}{% endmacro %} {{ _self.marco(1) }}',
                true
            ],

            // import
            ['{% import "marco.twig" as marco %} {{ marco.marco(1) }}', true],
            ['{% import "marco.twig" as marco %} {{ marco.marco() }}', false],
        ];
    }

    #[DataProvider('getImportedMacroTests')]
    public function test_importedMacro(string $template, bool $isValid): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );

        $this->env->createTemplate($template);

        self::assertEquals($isValid, empty($this->errors));

        if (!$isValid) {
            self::assertStringContainsStringIgnoringCase('too few', $this->errors[0]);
        }
    }

    public function test_circularImports(): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );

        $this->env->load('this.twig');

        self::addToAssertionCount(1);
    }
    
    public function test_multipleMacros(): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );

        $this->env->load('multiple-macros.twig');

        self::addToAssertionCount(1);
    }
    
    #[TestWith(["", true])]
    #[TestWith(["1", false])]
    public function test_conflictingLocalAndImportedMacro(string $marcoArgs, bool $isValid): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );
        
        $this->env->createTemplate(<<<TWIG
            {% from "multiple-macros.twig" import one %}
            {% macro marco() %}{% endmacro %}
            
            {{ one() }}
            {{ _self.marco($marcoArgs) }}
        TWIG);

        self::assertEquals($isValid, empty($this->errors));
    }

    public function test_duplicateMacroNamesInDifferentFiles(): void
    {
        $this->env->createTemplate(
            <<<EOF
            {% macro marco(polo) %}
                {{ polo }}
            {% endmacro %}
            {{ _self.marco('polo') }}
        EOF
        );

        $this->env->createTemplate(
            <<<EOF
            {% macro marco() %}
            {% endmacro %}
            {{ _self.marco() }}
        EOF
        );

        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_dynamicImport(): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );
        
        $this->env->createTemplate(<<<TWIG
            {% set template = "multiple-macros.twig" %}
            {% from template import one %}
            {{ one() }}
        TWIG);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
}
