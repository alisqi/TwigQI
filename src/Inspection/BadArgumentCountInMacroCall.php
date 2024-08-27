<?php

namespace AlisQI\TwigStan\Inspection;

use Twig\Environment;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class BadArgumentCountInMacroCall implements NodeVisitorInterface
{
    private const VAR_ARGS = 'varargs';

    /**
     * The top-level array has the macro's name as keys and its calls as value.
     * Each call is represented as an array containing the number of arguments
     * and the source location of the call.
     * 
     * For example:
     * ```php
     * ['marco' => [[1, 'tpl:1337']]];
     * ```
     * 
     * @var array<string, array<array{0: int, 1: string}>>
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
            foreach ($node->getNode('arguments') as $name => $default) {
                $signature[] = $name;
            }

            // add 'varargs' to signature if it's used anywhere (i.e., there's a NameExpression that uses it)
            if ($this->hasVarArgsNameExpressionDescendant($node)) {
                $signature[] = self::VAR_ARGS;
            }

            $this->checkCalls($macroName, $signature);
        } elseif ($node instanceof MethodCallExpression) {
            // when visiting a function call, log call
            $macroName = substr($node->getAttribute('method'), strlen('macro_'));
            
            $sourcePath = ($node->getSourceContext() ?? $node->getNode('node')->getSourceContext())?->getPath()
                ?? 'unknown';
            $location = "$sourcePath:{$node->getTemplateLine()}";
            
            $argumentCount = count($node->getNode('arguments')->getKeyValuePairs());

            $this->macroCalls[$macroName][] = [$argumentCount, $location];
        }
        
        return $node;
    }

    private function checkCalls(string $macro, array $signature): void
    {
        foreach (($this->macroCalls[$macro] ?? []) as [$argumentCount, $location]) {
            if (
                !in_array(self::VAR_ARGS, $signature, true) &&
                $argumentCount > count($signature)
            ) {
                trigger_error(
                    "Too many arguments ($argumentCount) for macro '$macro' (at $location)",
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

    private function hasVarArgsNameExpressionDescendant(Node $node): bool {
        if (
            $node instanceof NameExpression &&
            $node->getAttribute('name') === self::VAR_ARGS
        ) {
            return true;
        }
    
        foreach ($node as $childNode) {
            if ($this->hasVarArgsNameExpressionDescendant($childNode)) {
                return true;
            }
        }
    
        return false;
    }
}
