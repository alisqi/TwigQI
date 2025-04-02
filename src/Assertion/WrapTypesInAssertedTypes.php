<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Assertion;

use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

class WrapTypesInAssertedTypes implements NodeVisitorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

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

    public function getPriority(): int
    {
        return 0;
    }
}
