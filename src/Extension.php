<?php

declare(strict_types=1);

namespace AlisQI\TwigQI;

use AlisQI\TwigQI\Assertion\WrapTypesInAssertedTypes;
use AlisQI\TwigQI\Inspection\BadArgumentCountInMacroCall;
use AlisQI\TwigQI\Inspection\InvalidConstant;
use AlisQI\TwigQI\Inspection\InvalidDotOperation;
use AlisQI\TwigQI\Inspection\RequiredMacroArgumentAfterOptional;
use AlisQI\TwigQI\Inspection\UndeclaredVariableInMacro;
use AlisQI\TwigQI\Inspection\ValidTypes;
use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [
            new ValidTypes(),
            new InvalidDotOperation(),
            new WrapTypesInAssertedTypes(),

            new InvalidConstant(),

            new BadArgumentCountInMacroCall(),
            new RequiredMacroArgumentAfterOptional(),
            new UndeclaredVariableInMacro(),
        ];
    }
}
