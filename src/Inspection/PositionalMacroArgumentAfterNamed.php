<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class PositionalMacroArgumentAfterNamed implements NodeVisitorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroReferenceExpression) {
            if (!$this->checkCall($node)) {
                $this->logger->error(
                    sprintf("Positional macro argument after named (at %s)", new NodeLocation($node)),
                );
            }
        }

        return $node;
    }

    private function checkCall(MacroReferenceExpression $node): bool
    {
        $namedArgumentEncountered = false;
        foreach ($node->getNode('arguments')->getKeyValuePairs() as ['key' => $key]) {
            $name = $key->getAttribute('name');

            if (is_int($name)) {
                if ($namedArgumentEncountered) {
                    return false;
                }
            } else {
                $namedArgumentEncountered = true;
            }
        }

        return true;
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
