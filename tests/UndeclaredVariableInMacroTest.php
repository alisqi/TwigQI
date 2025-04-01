<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\UndeclaredVariableInMacro;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;

class UndeclaredVariableInMacroTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(LoggerInterface $logger): ExtensionInterface
    {
        return new class(
            $logger,
            [UndeclaredVariableInMacro::class]
        ) extends Extension {};
    }

    public function test_itDetectsNakedVariables()
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ [foo, bar] }}
            {% endmacro %}
        EOF);
        
        self::assertCount(2, $this->errors);
        
        self::assertStringContainsString('foo', $this->errors[0]);
        self::assertStringContainsString('marco', $this->errors[0]);
        
        self::assertStringContainsString('bar', $this->errors[1]);
        self::assertStringContainsString('marco', $this->errors[1]);
    }
    
    #[DataProvider('advancedVariableUsageExpressions')]
    public function test_itDetectsAdvancedVariableUsages(string $expression): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ $expression }}
            {% endmacro %}
        EOF);
        
        self::assertCount(1, $this->errors);
    }

    public static function advancedVariableUsageExpressions(): iterable
    {
        yield ['foo|abs'];
        yield ['max(foo)'];
        yield ['"#{foo}"'];
        yield ['{(foo): true}'];
    }

    public function test_itIgnoresNonMacroCode()
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}{% endmacro %}
            {{ whoopiedoo }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public function test_itRecognizesMacroArguments(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(foo, bar) %}
                {{ foo ~ bar }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public function test_itRecognizesSetVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% set foo, bar = true, true %}
                {{ foo ~ bar }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itDetectsUndeclaredVariableInSetTag(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% set foo = bar %}
            {% endmacro %}
        EOF);
        
        self::assertStringContainsString(
            'bar',
            current($this->errors) ?: '(no error)',
        );
        
        $this->errors = [];
        
        // now test for false positives using a macro argument and set variable
        $this->env->createTemplate(<<<EOF
            {% macro marco(bar) %}
                {% set baz = bar %}
                {% set foo = bar + baz %}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itReportsGlobalVariables(): void
    {
        $this->env->addGlobal('gloobar', true);
        
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                 {{ gloobar }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itSupportsSelf(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ _self }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itSupportsVarArgs(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ varargs|length }}
            {% endmacro %}
            {{ _self.marco(13, 37) }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itSupportsIsDefined(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ polo is defined }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itDetectsForLoopVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for key, value in {} %}
                    {{ [loop, key, value] }}
                {% endfor %}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->errors = [];
        
        // assert that _key is allowed inside for loop if key isn't declared
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for value in {} %}
                    {{ [loop, _key, value] }}
                {% endfor %}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->errors = [];
        
        // assert that loop variables are unset after the loop
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for key, value in {} %}{% endfor %}
                {{ [loop, key, value] }}
            {% endmacro %}
        EOF);
        
        self::assertCount(3, $this->errors);
        
        $this->errors = [];
        
        // same for _key
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for value in {} %}{% endfor %}
                {{ [_key] }}
            {% endmacro %}
        EOF);
        
        self::assertCount(1, $this->errors);
        
        $this->errors = [];
        
        // assert that we can redeclare variables in for loop (even if it might not be advisable)
        $this->env->createTemplate(<<<EOF
            {% macro marco(polo) %}
                {% for polo in [] %}{% endfor %}
                {{ polo }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itDetectsArrowFunctionVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ []|filter((key, value) => key and value) }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->errors = [];
        
        // assert that arrow function variables are unset after the function expression
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ []|filter((key, value) => key and value) }}
                {{ [key, value] }}
            {% endmacro %}
        EOF);
        
        self::assertCount(2, $this->errors);
        
        $this->errors = [];
        
        // assert that arrow function variables can use already-declared variable
        $this->env->createTemplate(<<<EOF
            {% macro marco(polo) %}
                {{ []|filter(polo => polo) }}
                {{ [polo] }}
            {% endmacro %}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
}
