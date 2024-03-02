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
    
    public function test_itDetectsNakedVariables()
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ [foo, bar] }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(2, $this->errors);
        
        self::assertStringContainsString('foo', $this->errors[0]);
        self::assertStringContainsString('marco', $this->errors[0]);
        
        self::assertStringContainsString('bar', $this->errors[1]);
        self::assertStringContainsString('marco', $this->errors[1]);
    }

    public function test_itDetectsAdvancedVariableUsages(): void
    {
        // filter input
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ foo|abs }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(1, $this->errors);
        
        $this->errors = [];
        
        // function argument
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ max(foo) }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(1, $this->errors);
        
        $this->errors = [];

        // string interpolation
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ "#{foo}" }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(1, $this->errors);
        
        $this->errors = [];
        
        // object keys
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ {(foo): true} }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(1, $this->errors);
    }

    public function test_itIgnoresNonMacroCode()
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}{% endmacro %}
            {{ whoopiedoo }}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
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
                    {{ [loop, key, value] }}
                {% endfor %}
            {% endmacro %}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->errors = [];
        
        // assert that loop variables are unset after the loop
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {% for key, value in {} %}{% endfor %}
                {{ [loop, key, value] }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(3, $this->errors);
    }
    
    public function test_itDetectsArrowFunctionVariables(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ []|filter((key, value) => key and value) }}
            {% endmacro %}
        EOF)->render();
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->errors = [];
        
        // assert that arrow function variables are unset after the function expression
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ []|filter((key, value) => key and value) }}
                {{ [key, value] }}
            {% endmacro %}
        EOF)->render();
        
        self::assertCount(2, $this->errors);
    }
}
