<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\InvalidNamedMacroArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;

class InvalidNamedMacroArgumentTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(LoggerInterface $logger): ExtensionInterface
    {
        return new class(
            $logger,
            [InvalidNamedMacroArgument::class]
        ) extends Extension {};
    }

    public static function getTests(): array
    {
        return [
            [
                <<<EOF
                {% macro marco(polo) %}{% endmacro %}
                {{ _self.marco(polo: 1) }}
                EOF,
                true
            ],
            
            [
                <<<EOF
                {% macro marco(polo) %}{% endmacro %}
                {{ _self.marco(nope: 1) }}
                EOF,
                false
            ],
            
            [
                <<<EOF
                {% macro marco(po, lo) %}{% endmacro %}
                {{ _self.marco(true, lo: 1) }}
                EOF,
                true
            ],
            
            [
                <<<EOF
                {% macro marco(po, lo) %}{% endmacro %}
                {{ _self.marco(true, nope: 1) }}
                EOF,
                false
            ],
            
            [
                <<<EOF
                {% macro marco(po, lo = true) %}{% endmacro %}
                {{ _self.marco(true, nope: 1) }}
                EOF,
                false
            ],
            
            // multiple macros
            [
                <<<EOF
                {% macro marco(polo) %}{% endmacro %}
                {% macro polo(marco) %}{% endmacro %}
                {{ _self.marco(polo: 1) }}
                {{ _self.polo(marco: 1) }}
                EOF,
                true
            ],
            [
                <<<EOF
                {% macro marco(polo) %}{% endmacro %}
                {% macro polo(marco) %}{% endmacro %}
                {{ _self.marco(polo: 1) }}
                {{ _self.polo( polo: 1) }}
                EOF,
                false
            ],
            
            // macro definition after reference
            [
                <<<EOF
                {{ _self.marco() }}
                {% macro marco() %}{% endmacro %}
                EOF,
                true
            ],
            
            [
                <<<EOF
                {{ _self.marco(nope: 1) }}
                {% macro marco(polo) %}{% endmacro %}
                EOF,
                false
            ],
        ];
    }

    #[DataProvider('getTests')]
    public function test_itValidatesNamedArguments(string $template, bool $isValid): void
    {
        $this->env->createTemplate($template);

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
    
    public static function getImportedMacroTests(): array
    {
        return [
            // from ... import
            ['{% from "marco.twig" import marco %} {{ marco(polo: 1) }}', true],
            ['{% from "marco.twig" import marco %} {{ marco(olop: 1) }}', false],

            // from ... import with alias
            ['{% from "marco.twig" import marco as polo %} {{ polo(polo: 1) }}', true],
            ['{% from "marco.twig" import marco as polo %} {{ polo(olop: 1) }}', false],

            [
                '{% from "marco.twig" import marco as polo %} {% macro marco(olop) %}{% endmacro %} {{ _self.marco(olop: 1) }}',
                true
            ],
            [
                '{% from "marco.twig" import marco as polo %} {% macro marco(olop) %}{% endmacro %} {{ _self.marco(polo: 1) }}',
                false
            ],

            // import
            ['{% import "marco.twig" as marco %} {{ marco.marco(polo: 1) }}', true],
            ['{% import "marco.twig" as marco %} {{ marco.marco(olop: 1) }}', false],
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
            self::assertStringContainsStringIgnoringCase('Invalid named macro argument', $this->errors[0]);
        }
    }
}
