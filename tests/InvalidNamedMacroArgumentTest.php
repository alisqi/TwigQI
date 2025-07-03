<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\InvalidNamedMacroArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;

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
}
