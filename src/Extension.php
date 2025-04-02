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
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;

class Extension extends AbstractExtension
{
    /** @var NodeVisitorInterface[] */
    private readonly array $inspections;

    /**
     * @param list<class-string<NodeVisitorInterface>> $inspections
     */
    public function __construct(
        LoggerInterface $logger,
        array|null $inspections = null,
    ) {
        $this->inspections = array_map(
            static fn(string $class) => new $class($logger),
            $inspections ?? $this->getDefaultInspections()
        );
    }
    
    public function getNodeVisitors(): array
    {
        return $this->inspections;
    }

    /**
     * @return list<class-string<NodeVisitorInterface>>
     */
    private function getDefaultInspections(): array
    {
        return [
            InvalidTypes::class,
            InvalidDotOperation::class,
            WrapTypesInAssertedTypes::class,

            InvalidConstant::class,
            InvalidEnumCase::class,

            BadArgumentCountInMacroCall::class,
            PositionalMacroArgumentAfterNamed::class,
            RequiredMacroArgumentAfterOptional::class,
            UndeclaredVariableInMacro::class,
        ];
    }
}
