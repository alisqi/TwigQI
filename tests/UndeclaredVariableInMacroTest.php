<?php

namespace AlisQI\TwigStan\Tests;

use AlisQI\TwigStan\Extension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class UndeclaredVariableInMacroTest extends TestCase
{
    private Environment $env;
    private array $errors;
    
    public function setUp(): void
    {
        $this->env = new Environment(new ArrayLoader());
        $this->env->addExtension(new Extension());
        
        $this->errors = [];
        set_error_handler(function (int $code, string $error) {
           $this->errors[] = $error;
        });
    }

    public function tearDown(): void
    {
        restore_error_handler();
    }
    
    public function test_itDetectsUndeclaredVariables()
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ foo }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(1, $this->errors);
        
        $error = current($this->errors);
        self::assertStringContainsString('foo', $error);
        self::assertStringContainsString('marco', $error);
    }

    public function test_itRecognizesMacroArguments(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(foo, bar) %}
                {{ foo ~ bar }}
            {% endmacro %}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public function test_itRecognizesSetVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% set foo, bar = true, true %}
                {{ foo ~ bar }}
            {% endmacro %}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itDetectsUndeclaredVariableInSetTag(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% set foo = bar %}
            {% endmacro %}
        EOF)->render();
        
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
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itDetectsGlobalVariables(): void
    {
        $this->env->addGlobal('gloobar', true);
        
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                 {{ gloobar }}
            {% endmacro %}
        EOF)->render();
        
        self::assertStringContainsString(
            'gloobar',
            current($this->errors) ?: '(no error)',
        );
    }
    
    public function test_itDetectsForLoopVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for key, value in {} %}
                    {{ loop.index }}: {{ key }} = {{ value }}
                {% endfor %}
            {% endmacro %}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
}
