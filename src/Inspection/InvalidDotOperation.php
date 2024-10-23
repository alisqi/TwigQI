<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use AlisQI\TwigQI\Helper\VariableTypeCollector;
use Twig\Environment;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Template;

class InvalidDotOperation implements NodeVisitorInterface
{
    private const UNSUPPORTED_TYPES = [
        'string',
        'number',
        'boolean',
    ];

    private VariableTypeCollector $variableTypeCollector;

    public function __construct()
    {
        $this->variableTypeCollector = new VariableTypeCollector();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        // reset state between templates
        if ($node instanceof ModuleNode) {
            $this->variableTypeCollector = new VariableTypeCollector();
        }

        if ($node instanceof TypesNode) {
            $this->variableTypeCollector->add($node);
        }

        if (
            $node instanceof GetAttrExpression &&
            $node->getAttribute('type') !== Template::ARRAY_CALL &&
            ($nameNode = $node->getNode('node')) instanceof NameExpression
        ) {
            $location = new NodeLocation($node);
            $name = $nameNode->getAttribute('name');
            $attribute = $node->getNode('attribute')->getAttribute('value');
            $this->checkOperation($name, $attribute, $location);
        }

        return $node;
    }

    private function checkOperation(string $name, string $attribute, NodeLocation $location): void
    {
        if (!$this->variableTypeCollector->isDeclared($name)) {
            return;
        }

        $type = $this->variableTypeCollector->getDeclaredType($name);

        if (in_array($type, self::UNSUPPORTED_TYPES)) {
            trigger_error(
                sprintf('Invalid dot operation on unsupported type \'%s\' (at %s)', $type, $location),
                E_USER_ERROR
            );
        }
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
