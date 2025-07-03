<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class InvalidNamedMacroArgument implements NodeVisitorInterface
{
    /** @var array<string, MacroNode> */
    private array $macroNodes = [];
    
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->macroNodes += iterator_to_array($node->getNode('macros'));
        }
        
        if ($node instanceof MacroReferenceExpression) {
            $this->checkReference($node);
        }
        
        return $node;
    }

    private function checkReference(MacroReferenceExpression $node): void
    {
        $macroName = substr($node->getAttribute('name'), strlen('macro_'));
        try {
            $signature = $this->createSignature($macroName);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error(
                 sprintf(
                    "{$e->getMessage()} (at %s)",
                    new NodeLocation($node)
                )
            );
            return;
        }

        if (!empty($invalidArguments = $this->checkCall($node, $signature))) {
            $this->logger->error(
                sprintf(
                    "Invalid named macro argument(s) %s (at %s)",
                    implode(', ', $invalidArguments),
                    new NodeLocation($node)
                ),
            );
        }
    }

    /**
     * @return string[]
     * @throws \InvalidArgumentException
     */
    private function createSignature(string $macroName): array
    {
        $macroNode = $this->macroNodes[$macroName] ?? throw new \InvalidArgumentException("Unknown macro '$macroName'");

        $signature = [];
        foreach ($macroNode->getNode('arguments')->getKeyValuePairs() as ['key' => $key]) {
            $signature[] = $key->getAttribute('name');
        }
        return $signature;
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
