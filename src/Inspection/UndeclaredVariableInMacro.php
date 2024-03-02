<?php

namespace AlisQI\TwigStan\Inspection;

use Twig\Environment;
use Twig\Node\Expression\NameExpression;
use Twig\Node\ForNode;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\NodeVisitor\AbstractNodeVisitor;

class UndeclaredVariableInMacro extends AbstractNodeVisitor
{
    private ?string $currentMacro = null;

    /** @var string[] */
    private array $declaredVariableNames = [];

    protected function doEnterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroNode) {
            // when entering a macro, add its arguments to declared variables
            $this->currentMacro = $node->getAttribute('name');
            $this->declaredVariableNames = [];

            foreach ($node->getNode('arguments') as $name => $default) {
                $this->declaredVariableNames[] = $name;
            }
        } else if ($node instanceof SetNode) {
            // when entering a `set` tag, add its variables to declared variables
            foreach ($node->getNode('names') as $nameNode) {
                $this->declaredVariableNames[] = $nameNode->getAttribute('name');
            }
        } else if ($node instanceof ForNode) {
            // when entering a `for` tag, add its declared variables and 'loop' to declared variables
            $this->declaredVariableNames += $this->getForNodeVariables($node);
        } else if (
            $this->currentMacro &&
            $node instanceof NameExpression &&
            $node->isSimple()
        ) {
            // when visiting a (variable) name expression, test whether it's already declared
            $this->checkVariableIsDeclared($node->getAttribute('name'));
        }

        return $node;
    }

    protected function doLeaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroNode) {
            $this->currentMacro = null;
            // no need to reset $this->declaredVariableNames
        } elseif ($node instanceof ForNode) {
            // remove 'loop' variable
            $forVariables = $this->getForNodeVariables($node);
            
            $this->declaredVariableNames = array_filter(
                $this->declaredVariableNames,
                static fn($variable) => !in_array($variable, $forVariables)
            );
        }

        return $node;
    }

    /**
     * @return string[]
     */
    private function getForNodeVariables(ForNode $node): array
    {
        $variables = [
            'loop',
            $valueVariable = $node->getNode('value_target')->getAttribute('name'),
        ];
        if ($valueVariable !== ($keyVariable = $node->getNode('key_target')->getAttribute('name'))) {
            $variables[] = $keyVariable;
        }
        return $variables;
    }

    private function checkVariableIsDeclared(string $variableName): void
    {
        if (!in_array($variableName, $this->declaredVariableNames, false)) {
            $error = sprintf(
                'The macro "%s" uses an undeclared variable named "%s".',
                $this->currentMacro,
                $variableName
            );

            $this->currentMacro = null;

            trigger_error($error, E_USER_WARNING); // some environments might throw Exceptions in their error handlers, so we should assume this line throws itself
        }
    }

    public function getPriority(): int
    {
        return 0;
    }
}
