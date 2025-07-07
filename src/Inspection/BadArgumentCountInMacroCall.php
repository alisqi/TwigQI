<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\MacroReferenceVisitor;
use AlisQI\TwigQI\Helper\NodeLocation;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\MacroNode;
use Twig\Node\Node;

class BadArgumentCountInMacroCall extends MacroReferenceVisitor
{
    protected function checkReference(MacroReferenceExpression $node): void
    {
        $location = new NodeLocation($node);
        
        $macroName = substr($node->getAttribute('name'), strlen('macro_'));
        try {
            $signature = $this->createSignature($macroName);
        } catch (\InvalidArgumentException) {
            return;
        }
        
        $argumentCount = count($node->getNode('arguments')->getKeyValuePairs());
        
        // check for too _many_ arguments
        $usesVarArgs = array_filter(
            $signature,
            static fn(array $argument) => $argument['name'] === MacroNode::VARARGS_NAME
        );
        if (
            !$usesVarArgs &&
            $argumentCount > count($signature)
        ) {
            $this->logger->warning(
                "Too many arguments ($argumentCount) for macro '$macroName' (at $location)",
            );
        }

        // check for too _few_ arguments
        $requiredArguments = array_filter(
            $signature,
            static fn(array $param) => $param['required']
        );
        if ($argumentCount < count($requiredArguments)) {
            $this->logger->warning(
                "Too few arguments ($argumentCount) for macro '$macroName' (at $location)",
            );
        }
    }

    /**
     * @return array{name: string, required: bool}
     * @throws \InvalidArgumentException
     */
    private function createSignature(string $macroName): array
    {
        $macroNode = $this->macroNodes[$macroName] ?? throw new \InvalidArgumentException("Unknown macro '$macroName'");

        $signature = [];
        foreach ($macroNode->getNode('arguments')->getKeyValuePairs() as ['key' => $key, 'value' => $default]) {
            $name = $key->getAttribute('name');
            $signature[] = [
                'name' => $name,
                'required' => $default->hasAttribute('is_implicit') // if attr is set, it's always true
            ];
        }

        // add 'varargs' to signature if it's used anywhere (i.e., there's a ContextVariable that uses it)
        if ($this->hasVarArgsContextVariableDescendant($macroNode)) {
            $signature[] = ['name' => MacroNode::VARARGS_NAME, 'required' => false];
        }
        return $signature;
    }

    private function hasVarArgsContextVariableDescendant(Node $node): bool
    {
        if (
            $node instanceof ContextVariable &&
            $node->getAttribute('name') === MacroNode::VARARGS_NAME
        ) {
            return true;
        }

        foreach ($node as $childNode) {
            if ($this->hasVarArgsContextVariableDescendant($childNode)) {
                return true;
            }
        }

        return false;
    }
}
