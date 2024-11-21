<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class BadArgumentCountInMacroCall implements NodeVisitorInterface
{
    /**
     * The top-level array has the macro's name as keys and its calls as value.
     * Each call is represented as an array containing the number of arguments
     * and the source location of the call.
     * 
     * For example:
     * ```php
     * ['marco' => [[1, NodeLocation]]];
     * ```
     * 
     * @var array<string, array<array{0: int, 1: NodeLocation}>>
     */
    private array $macroCalls = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        /*
         * `MethodCallExpression`s are entered before `MacroNode`s, even if the call precedes the macro tag!
         * Therefore, we log `MethodCallExpression`s and check them when entering the `MacroNode`s.
         */
        if ($node instanceof MacroNode) {
            // when visiting a macro declaration, check logged calls
            $macroName = $node->getAttribute('name');

            $signature = [];
            foreach ($node->getNode('arguments')->getKeyValuePairs() as ['key' => $key, 'value' => $default]) {
                $name = $key->getAttribute('name');
                $signature[] = [
                    'name'     => $name,
                    'required' => $default->hasAttribute('is_implicit') // if attr is set, it's always true
                ];
            }

            // add 'varargs' to signature if it's used anywhere (i.e., there's a ContextVariable that uses it)
            if ($this->hasVarArgsContextVariableDescendant($node)) {
                $signature[] = ['name' => MacroNode::VARARGS_NAME, 'required' => false];
            }

            $this->checkCalls($macroName, $signature);
            
            unset($this->macroCalls[$macroName]); // remove logged calls to prevent collisions (macro with identical name in another template)
        } elseif ($node instanceof MacroReferenceExpression) {
            // when visiting a function call, log call
            $macroName = substr($node->getAttribute('name'), strlen('macro_'));

            $location = new NodeLocation($node);
            
            $argumentCount = count($node->getNode('arguments')->getKeyValuePairs());

            $this->macroCalls[$macroName][] = [$argumentCount, $location];
        }
        
        return $node;
    }

    /**
     * @param array<array{name: string, required: bool}> $signature
     */
    private function checkCalls(string $macro, array $signature): void
    {
        foreach (($this->macroCalls[$macro] ?? []) as [$argumentCount, $location]) {
            // check for too _many_ arguments
            $usesVarArgs = array_filter(
                $signature,
                static fn (array $argument) => $argument['name'] === MacroNode::VARARGS_NAME
            );
            if (
                !$usesVarArgs &&
                $argumentCount > count($signature)
            ) {
                trigger_error(
                    "Too many arguments ($argumentCount) for macro '$macro' (at $location)",
                    E_USER_WARNING
                );
            }
            
            // check for too _few_ arguments
            $requiredArguments = array_filter(
                $signature,
                static fn(array $param) => $param['required']
            );
            if ($argumentCount < count($requiredArguments)) {
                trigger_error(
                    "Too few arguments ($argumentCount) for macro '$macro' (at $location)",
                    E_USER_WARNING
                );
            }
        }
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function hasVarArgsContextVariableDescendant(Node $node): bool {
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
