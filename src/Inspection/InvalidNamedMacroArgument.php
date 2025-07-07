<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\MacroReferenceVisitor;
use AlisQI\TwigQI\Helper\NodeLocation;
use Twig\Node\Expression\MacroReferenceExpression;

class InvalidNamedMacroArgument extends MacroReferenceVisitor
{
    protected function checkReference(MacroReferenceExpression $node): void
    {
        $macroName = substr($node->getAttribute('name'), strlen('macro_'));
        try {
            $signature = $this->createSignature($macroName);
        } catch (\InvalidArgumentException $e) {
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
}
