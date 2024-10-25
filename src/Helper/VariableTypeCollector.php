<?php

namespace AlisQI\TwigQI\Helper;

use Twig\Node\TypesNode;

class VariableTypeCollector
{
    /** @var array<string, list<string>> */
    private array $variableTypes = [];

    public function add(TypesNode $node): void
    {
        foreach ($node->getAttribute('mapping') as $name => ['type' => $type]) {
            $this->variableTypes[$name] = [$type];
        }
    }

    public function push(string $variableName, string $type): void
    {
        if (!array_key_exists($variableName, $this->variableTypes)) {
            $this->variableTypes[$variableName] = [];
        }
        $this->variableTypes[$variableName][] = $type;
    }

    public function pop(string $variableName): ?string
    {
        return array_pop($this->variableTypes[$variableName]);
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
        if (!$this->isDeclared($variableName)) {
            return null;
        }

        return end($this->variableTypes[$variableName]);
    }
}