<?php declare(strict_types=1);

use AlisQI\TwigQI\Tests\AbstractTestCase;

class RequiredMacroArgumentAfterOptionalTest extends AbstractTestCase
{
    public static function getValidOrderTests(): array
    {
        return [
            ['{% macro marco() %}{% endmacro %}'],
            ['{% macro marco(po = true) %}{% endmacro %}'],
            ['{% macro marco(po, lo = true) %}{% endmacro %}'],
        ];
    }

    /** @dataProvider getValidOrderTests */
    public function test_itDoesNotWarnForProperOrder(string $template): void
    {
        $this->env->createTemplate($template);

        self::assertEmpty($this->errors, implode(', ', $this->errors));
    }

    public static function getInvalidOrderTests(): array
    {
        return [
            [
                '{% macro marco(po = true, lo) %}{% endmacro %}',
                "'lo' is required, but previous"
            ],
            [
                '{% macro marco(po, lo = true, polo) %}{% endmacro %}',
                "'polo' is required, but previous"
            ],
        ];
    }

    /** @dataProvider getInvalidOrderTests */
    public function test_itWarnsForRequiredAfterOptionalArgument(string $template, $error): void
    {
        $this->env->createTemplate($template);

        self::assertCount(1, $this->errors);

        self::assertStringContainsString('marco', $this->errors[0]);
        self::assertStringContainsStringIgnoringCase($error, $this->errors[0]);
    }

}
