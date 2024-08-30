<?php

namespace AlisQI\TwigQI\Inspection;

use Twig\Environment;
use Twig\Node\Expression\MethodCallExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class RequiredMacroArgumentAfterOptional implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroNode) {
            $sourcePath = ($node->getSourceContext() ?? $node->getNode('node')->getSourceContext())?->getPath()
                ?? 'unknown';
            $location = "$sourcePath:{$node->getTemplateLine()}";

            $macroName = $node->getAttribute('name');

            $previousArgumentIsRequired = null;
            foreach ($node->getNode('arguments') as $name => $default) {
                $currentArgumentIsRequired = $default->hasAttribute('is_implicit'); // if attr is set, it's always true

                if ($currentArgumentIsRequired && $previousArgumentIsRequired === false) {
                    trigger_error(
                        "Macro '$macroName' argument '$name' is required, but previous isn't (at $location)",
                        E_USER_WARNING
                    );

                    break; // skip any following arguments
                }

                $previousArgumentIsRequired = $currentArgumentIsRequired;
            }
        }

        return $node;
    }


    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
