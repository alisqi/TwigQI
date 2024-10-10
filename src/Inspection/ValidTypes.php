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
            $sourcePath = ($node->getSourceContext() ?? $node->getNode('node')->getSourceContext())?->getPath()
                ?? 'unknown';
            $location = "$sourcePath:{$node->getTemplateLine()}";

            foreach ($node->getAttribute('mapping') as $name => ['type' => $type]) {
                $this->validateType($name, $type, $location);
            }
        }

        return $node;
    }

    private function validateType(string $name, string $type, string $location): void
    {
        if (in_array($type, self::BASIC_TYPES)) {
            return;
        }

        if ($replacement = (self::DEPRECATED_TYPES[$type] ?? null)) {
            trigger_error(
                "Deprecated type '$type' used. Use '$replacement' instead.",
                E_USER_DEPRECATED
            );
            return;
        }

        if (preg_match_all(self::FQN_REGEX, $type)) {
            return;
        }

        trigger_error(
            "Invalid type '$type' for variable '$name' (at $location)",
            E_USER_ERROR
        );
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
