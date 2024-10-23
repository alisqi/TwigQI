<?php

namespace AlisQI\TwigQI\Helper;

use Twig\Node\TypesNode;

class VariableTypeCollector
{
    /** @var array<string, string> */
    private array $variableTypes = [];

    public function add(TypesNode $node): void
    {
        foreach ($node->getAttribute('mapping') as $name => ['type' => $type]) {
            $this->variableTypes[$name] = $type;
        }
    }

    /** list<string> */
    public function getDeclaredNames(): array
    {
        return array_keys($this->variableTypes);
    }

    public function isDeclared(string $variableName): bool
    {
        return array_key_exists($variableName, $this->variableTypes);
    }

    public function getDeclaredType(string $variableName): ?string
    {
        return $this->variableTypes[$variableName]
            ?? throw new \InvalidArgumentException(sprintf('Variable "%s" is not declared.', $variableName));
    }
}