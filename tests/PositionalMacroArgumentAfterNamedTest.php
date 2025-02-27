<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\PositionalMacroArgumentAfterNamed;
use Twig\Extension\ExtensionInterface;

class PositionalMacroArgumentAfterNamedTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(): ExtensionInterface
    {
        return new class([
            new PositionalMacroArgumentAfterNamed()
        ]) extends Extension {};
    }

    public function test_itSupportsNamedArguments(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(po, lo) %}{% endmacro %}
            {{ _self.polo(po=13, lo: 37) }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itErrorsForPositionalArgumentAfterNamed(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(po, lo) %}{% endmacro %}
            {{ _self.polo(po: 13, 37) }}
        EOF);
        
        self::assertNotEmpty($this->errors);
    }

}
