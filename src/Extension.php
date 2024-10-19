<?php

namespace AlisQI\TwigQI;

use AlisQI\TwigQI\Assertion\WrapTypesInAssertedTypes;
use AlisQI\TwigQI\Inspection\BadArgumentCountInMacroCall;
use AlisQI\TwigQI\Inspection\InvalidConstant;
use AlisQI\TwigQI\Inspection\RequiredMacroArgumentAfterOptional;
use AlisQI\TwigQI\Inspection\UndeclaredVariableInMacro;
use AlisQI\TwigQI\Inspection\ValidTypes;
use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [
            new BadArgumentCountInMacroCall(),
            new InvalidConstant(),
            new RequiredMacroArgumentAfterOptional(),
            new UndeclaredVariableInMacro(),
            new ValidTypes(),
            new WrapTypesInAssertedTypes(),
        ];
    }
}
