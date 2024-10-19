<?php

namespace AlisQI\TwigQI\Assertion;

use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

class WrapTypesInAssertedTypes implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof TypesNode) {
            return new AssertedTypesNode($node);
        }

        return $node;
    }

    public function getPriority()
    {
        return 0;
    }
}