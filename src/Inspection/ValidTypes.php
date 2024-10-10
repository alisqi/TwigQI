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
            if (!in_array($type, self::BASIC_TYPES)) {
                $errors[] = "Invalid type '$type' for variable '$name'";
            }
        }

        foreach ($errors as $error) {
            trigger_error("Invalid types: $error (at $location)", E_USER_ERROR);
        }
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
