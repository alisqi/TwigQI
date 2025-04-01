<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use AlisQI\TwigQI\Extension;
use AlisQI\TwigQI\Inspection\InvalidEnumCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Twig\Extension\ExtensionInterface;

class InvalidEnumCaseTest extends AbstractTestCase
{
    protected function createUniqueExtensionClass(LoggerInterface $logger): ExtensionInterface
    {
        return new class(
            $logger,
            [InvalidEnumCase::class],
        ) extends Extension {};
    }

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

    #[DataProvider('getEnomCases')]
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
