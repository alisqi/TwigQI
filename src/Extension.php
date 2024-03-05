<?php

namespace AlisQI\TwigStan;

use AlisQI\TwigStan\Inspection\BadArgumentCountInMacroCall;
use AlisQI\TwigStan\Inspection\UndeclaredVariableInMacro;
use Twig\Extension\AbstractExtension;

class Extension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [
            new BadArgumentCountInMacroCall(),
            new UndeclaredVariableInMacro(),
        ];
    }
}
