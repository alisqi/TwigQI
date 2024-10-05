<?php

namespace AlisQI\TwigQI\Tests;

use Twig\Loader\FilesystemLoader;

class BadArgumentCountInMacroCallTest extends AbstractTestCase
{
    public function test_itDoesNotWarnForMatchingArgumentNumber(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}{% endmacro %}
            {% macro polo(arg, gra) %}{% endmacro %}
            {{ _self.marco() }}
            {{ _self.polo(13, 37) }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itWarnsForTooManyArguments(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}{% endmacro %}
            {{ _self.marco(1337) }}
        EOF);
        
        self::assertCount(1, $this->errors);
        
        self::assertStringContainsString('marco', $this->errors[0]);
        self::assertStringContainsStringIgnoringCase('too many', $this->errors[0]);
    }
    
    public function test_itSupportsVarArgs(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
                {{ varargs|length }}
            {% endmacro %}
            {{ _self.marco() }}
            {{ _self.marco(1337) }}
            {{ _self.marco(13, 37) }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
        
        $this->env->createTemplate(<<<EOF
            {% macro marco2(polo) %}
                {% if polo %}
                    {{ varargs|length }}
                {% endif %}
            {% endmacro %}
            {{ _self.marco2(13, 37) }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
    
    public function test_itSupportsArrayDefaults(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(polo = []) %}
                {{ polo|length }}
            {% endmacro %}
            {{ _self.marco() }}
        EOF);
        
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
     * @dataProvider getTooFewArgumentsTestCases
     * @param list<string> $errors
     */
    public function test_itWarnsForTooFewArguments(string $template, array $errors): void
    {
        $this->env->createTemplate($template);
        
        self::assertCount(count($errors), $this->errors);
        
        foreach ($errors as $i => $error) {
            self::assertStringContainsString($error, $this->errors[$i]);
        }
    }

    public function test_importedMacro(): void
    {
        $this->env->setLoader(
            new FilesystemLoader(__DIR__ . '/fixtures')
        );
        
        $this->env->render('importedMacro.twig');
        
        self::assertCount(2, $this->errors);
        
        self::assertStringContainsString('importedMacro', $this->errors[0]);
        self::assertStringContainsString('local', $this->errors[0]);
        self::assertStringContainsStringIgnoringCase('too many', $this->errors[0]);
        
        self::assertStringContainsString('importedMacro', $this->errors[1]);
        self::assertStringContainsString('marco', $this->errors[1]);
        self::assertStringContainsStringIgnoringCase('too many', $this->errors[1]);
    }

    public function test_duplicateMacroNamesInDifferentFiles(): void
    {
        $this->env->createTemplate(<<<EOF
            {% macro marco(polo) %}
                {{ polo }}
            {% endmacro %}
            {{ _self.marco('polo') }}
        EOF);
        
        $this->env->createTemplate(<<<EOF
            {% macro marco() %}
            {% endmacro %}
            {{ _self.marco() }}
        EOF);
        
        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }
}
