<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use Twig\Environment;
use Twig\Node\Expression\ArrowFunctionExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ForNode;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\NodeVisitor\NodeVisitorInterface;

class UndeclaredVariableInMacro implements NodeVisitorInterface
{
    private const SPECIAL_VARIABLE_NAMES = [
        'varargs',
    ];
    
    private ?string $currentMacro = null;

    /**
     * A stack of variable names.
     * This can contain duplicates due to for loops or arrow functions
     * redeclaring variables.
     * 
     * @var string[]
     */
    private array $declaredVariableNames = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroNode) {
            $this->currentMacro = $node->getAttribute('name');
            $this->declaredVariableNames = array_keys($env->getGlobals()); // reset declared variables (macro arguments will be added below)
        }
        
        $this->declaredVariableNames = array_merge(
            $this->declaredVariableNames,
            $this->getDeclaredVariablesFor($node)
        );
        
        if (
            $this->currentMacro &&
            $node instanceof ContextVariable
        ) {
            $this->checkVariableIsDeclared($node);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroNode) {
            $this->currentMacro = null;
            // no need to unset any variables, as that will happen in doEnterNode
            return $node;
        }

        if (
            $node instanceof ForNode ||
            $node instanceof ArrowFunctionExpression
        ) {
            if (!empty($variablesToUnset = $this->getDeclaredVariablesFor($node))) {
                array_splice($this->declaredVariableNames, -count($variablesToUnset));
            }
        }

        return $node;
    }

    /**
     * @return string[]
     */
    private function getDeclaredVariablesFor(Node $node): array
    {
        $variables = [];
        
        // Strategy pattern would be overkill here (for now, at least)
        if ($node instanceof MacroNode) {
            foreach ($node->getNode('arguments')->getKeyValuePairs() as ['key' => $key]) {
                $variables[] = $key->getAttribute('name');
            }
        } else if (
            $node instanceof SetNode ||
            $node instanceof ArrowFunctionExpression
        ) {
            foreach ($node->getNode('names') as $nameNode) {
                $variables[] = $nameNode->getAttribute('name');
            }
        } else if ($node instanceof ForNode) {
            $variables += [
                'loop',
                $node->getNode('key_target')->getAttribute('name'), // if loop doesn't declare key variable, _key is added automatically
                $node->getNode('value_target')->getAttribute('name'),
            ];
        }
        
        return $variables;
    }

    private function checkVariableIsDeclared(ContextVariable $node): void
    {
        $variableName = $node->getAttribute('name');

        if (
            !$node->getAttribute('is_defined_test') &&
            $variableName !== '_self' &&
            !str_starts_with($variableName, '__internal') &&
            !in_array($variableName, $this->declaredVariableNames, false) &&
            !in_array($variableName, self::SPECIAL_VARIABLE_NAMES, false)
        ) {
            trigger_error(
                sprintf(
                    'The macro "%s" (%s:%d) uses an undeclared variable named "%s".',
                    $this->currentMacro,
                    $node->getSourceContext()?->getPath() ?? '',
                    $node->getTemplateLine(),
                    $variableName,
                ),
                E_USER_WARNING
            );
        }
    }

    public function getPriority(): int
    {
        return 0;
    }
}
