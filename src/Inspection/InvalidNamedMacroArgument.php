<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class InvalidNamedMacroArgument implements NodeVisitorInterface
{
    private array $macroReferences = [];
    
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        // We're visiting the macro calls _before_ their definition, so we need to first store the calls
        // and then validate them once we run across the matching definition.
        
        if ($node instanceof MacroReferenceExpression) {
            $this->macroReferences[] = $node;
        }
        
        if ($node instanceof MacroNode) {
            $this->checkMacro($node);
        }

        return $node;
    }

    private function checkMacro(MacroNode $node): void
    {
        $signature = [];
        foreach ($node->getNode('arguments')->getKeyValuePairs() as ['key' => $key]) {
            $signature[] = $key->getAttribute('name');
        }

        foreach ($this->macroReferences as $macroReference) {
            if ($macroReference->getAttribute('name') !== ('macro_' . $node->getAttribute('name'))) {
                continue;
            }

            if (!empty($invalidArguments = $this->checkCall($macroReference, $signature))) {
                $this->logger->error(
                    sprintf(
                        "Invalid named macro argument(s) %s (at %s)",
                        implode(', ', $invalidArguments),
                        new NodeLocation($node)
                    ),
                );
            }
        }
    }

    /** @return string[] */
    private function checkCall(MacroReferenceExpression $reference, array $signature): array
    {
        $invalidArguments = [];
        foreach ($reference->getNode('arguments')->getKeyValuePairs() as ['key' => $key]) {
            $name = $key->getAttribute('name');

            if (!is_int($name)) { // not a positional argument (and therefore named)
                if (!in_array($name, $signature, true)) {
                    $invalidArguments[] = $name;
                }
            }
        }

        return $invalidArguments;
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
