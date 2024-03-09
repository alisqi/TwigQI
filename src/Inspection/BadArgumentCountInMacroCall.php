<?php

namespace AlisQI\TwigStan\Inspection;

use Twig\Environment;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\AbstractNodeVisitor;

class BadArgumentCountInMacroCall extends AbstractNodeVisitor
{
    private self $noDefaultValueSymbol;
    
    /**
     * The top-level array has the macro's identifier as keys and their signature as value
     * The signature has argument names as keys and default value () as value.
     * We use $this to denote the absence of a default value since `null` can be a default. 
     * 
     * For example:
     * ```php
     * ['tpl.twig:marco' => ['arg1' => $this, 'arg2' => false]];
     * ```
     * 
     * @var array<string, array<string, mixed>>
     */
    private array $macroSignatures = [];
    
    public function __construct()
    {
        $this->noDefaultValueSymbol = $this;
    }

    protected function doEnterNode(Node $node, Environment $env): Node
    {
        /*
         * `MethodCallExpression`s are entered before `MacroNode`s.
         * Therefore, we query `ModuleNode`s to create macro signatures.
         */
        
        if ($node instanceof ModuleNode) {
            foreach ($node->getNode('macros') as $macroNode) {
                $macroName = $macroNode->getAttribute('name');
                $signature = [];
                foreach ($macroNode->getNode('arguments') as $name => $default) {
                    // TODO: Uh oh, looks like we can't distinguish between no default and default = null!
                    //  That's because macro arguments technically always have `null` as their default.
                    //  Let's be opinionated here and make developers declare these explicitly, meaning arguments
                    //  *without* a default are considered required.
                    $signature[$name] = $default->getAttribute('value') ?? $this->noDefaultValueSymbol;
                }
                
                $this->macroSignatures[$macroName] = $signature;
            }
        } elseif ($node instanceof MethodCallExpression) {
            // when visiting a function call, check argument count
            $macroName = substr($node->getAttribute('method'), strlen('macro_'));
            if (null !== $signature = ($this->macroSignatures[$macroName] ?? null)) {
                $arguments = $node->getNode('arguments')->getKeyValuePairs();
                $argumentCount = count($arguments);
                
                // TODO: this doesn't actually work. See above for explanation
                $requiredArguments = array_filter(
                    $signature,
                    fn($default) => $default !== $this->noDefaultValueSymbol
                );
                
                if ($argumentCount < count($requiredArguments)) {
                    trigger_error(
                        "Too few arguments ($argumentCount) for macro '$macroName'",
                        E_USER_WARNING
                    );
                } elseif ($argumentCount > count($signature)) {
                    trigger_error(
                        "Too many arguments ($argumentCount) for macro '$macroName'",
                        E_USER_WARNING
                    );
                }
            }
        }
        
        return $node;
    }

    protected function doLeaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
