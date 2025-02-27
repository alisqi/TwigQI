<?php

declare(strict_types=1);

namespace AlisQI\TwigQI;

use AlisQI\TwigQI\Assertion\WrapTypesInAssertedTypes;
use AlisQI\TwigQI\Inspection\BadArgumentCountInMacroCall;
use AlisQI\TwigQI\Inspection\InvalidConstant;
use AlisQI\TwigQI\Inspection\InvalidDotOperation;
use AlisQI\TwigQI\Inspection\InvalidEnumCase;
use AlisQI\TwigQI\Inspection\PositionalMacroArgumentAfterNamed;
use AlisQI\TwigQI\Inspection\RequiredMacroArgumentAfterOptional;
use AlisQI\TwigQI\Inspection\UndeclaredVariableInMacro;
use AlisQI\TwigQI\Inspection\InvalidTypes;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;

class Extension extends AbstractExtension
{
    /**
     * @var NodeVisitorInterface[]
     */
    private array $inspections;

    public function __construct(array|null $inspections = null) {
        $this->inspections = $inspections ?? $this->getDefaultInspections();
    }
    
    public function getNodeVisitors(): array
    {
        return $this->inspections;
    }
    
    private function getDefaultInspections(): array
    {
        return [
            new InvalidTypes(),
            new InvalidDotOperation(),
            new WrapTypesInAssertedTypes(),

            new InvalidConstant(),
            new InvalidEnumCase(),

            new BadArgumentCountInMacroCall(),
            new PositionalMacroArgumentAfterNamed(),
            new RequiredMacroArgumentAfterOptional(),
            new UndeclaredVariableInMacro(),
        ];
    }
}
