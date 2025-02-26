<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

class InvalidEnumCaseTest extends AbstractTestCase
{
    public static function getEnomCases(): array
    {
        $enum = '\\\\AlisQI\\\\TwigQI\\\\Tests\\\\Type\\\\Enom';

        return [
            [$enum, 'This', true],
            [$enum, 'That', true],
            [$enum, 'Invalid', false],
            [$enum, 'cases', true],
        ];
    }

    /** @dataProvider getEnomCases */
    public function test_itValidatesEnumCases(string $enum, string $case, bool $isValid): void
    {
        $this->env->createTemplate("{{ enum('$enum').$case }}");

        self::assertEquals(
            $isValid,
            empty($this->errors),
            implode(', ', $this->errors)
        );
    }
}
