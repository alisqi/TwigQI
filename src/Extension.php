<?php

namespace AlisQI\TwigQI;

use AlisQI\TwigQI\Inspection\BadArgumentCountInMacroCall;
use AlisQI\TwigQI\Inspection\RequiredMacroArgumentAfterOptional;
use AlisQI\TwigQI\Inspection\UndeclaredVariableInMacro;
use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [
            new BadArgumentCountInMacroCall(),
            new RequiredMacroArgumentAfterOptional(),
            new UndeclaredVariableInMacro(),
        ];
    }
}
