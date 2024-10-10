<?php

namespace AlisQI\TwigQI\Inspection;

use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

class ValidTypes implements NodeVisitorInterface
{
    private const BASIC_TYPES = [
        'string',
        'number',
        'boolean',
        'null',
        'iterable',
        'object',
    ];

    private const DEPRECATED_TYPES = [
        'bool' => 'boolean',
        'int' => 'number',
        'float' => 'number',
    ];

    private const FQN_REGEX = '/^\\\\[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof TypesNode) {
            $this->checkTypes($node);
        }

        return $node;
    }

    private function checkTypes(TypesNode $node): void
    {
        $sourcePath = ($node->getSourceContext() ?? $node->getNode('node')->getSourceContext())?->getPath()
            ?? 'unknown';
        $location = "$sourcePath:{$node->getTemplateLine()}";

        $errors = [];
        foreach ($node->getAttribute('mapping') as $name => ['type' => $type]) {
            if (!$this->isValidType($type)) {
                $errors[] = "Invalid type '$type' for variable '$name'";
            }
        }

        foreach ($errors as $error) {
            trigger_error("Invalid types: $error (at $location)", E_USER_ERROR);
        }
    }

    private function isValidType(string $type): bool
    {
        if (in_array($type, self::BASIC_TYPES)) {
            return true;
        }
       
        if (array_key_exists($type, self::DEPRECATED_TYPES)) {
            $replacement = self::DEPRECATED_TYPES[$type];
            trigger_error("Deprecated type '$type' used. Use '$replacement' instead.", E_USER_DEPRECATED);
            return true;
        }

        if (preg_match_all(self::FQN_REGEX, $type)) {
            return true;
        }

        return false;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

}
