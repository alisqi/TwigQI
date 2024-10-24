<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use AlisQI\TwigQI\Helper\VariableTypeCollector;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use Twig\Environment;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\MacroNode;
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

    private VariableTypeCollector $globalVariableTypeCollector;
    private ?VariableTypeCollector $scopedVariableTypeCollector = null;

    public function __construct()
    {
        $this->globalVariableTypeCollector = new VariableTypeCollector();
    }

    private function getCurrentVariableTypeCollector(): VariableTypeCollector
    {
        return $this->scopedVariableTypeCollector
            ?? $this->globalVariableTypeCollector;
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        // reset state between templates
        if ($node instanceof ModuleNode) {
            $this->globalVariableTypeCollector = new VariableTypeCollector();
            $this->scopedVariableTypeCollector = null;
        }

        if ($node instanceof MacroNode) {
            $this->scopedVariableTypeCollector = new VariableTypeCollector();
            // Note: we don't have to unset this collector because the Twig\NodeTraverser always visits macros _last_
        }

        if ($node instanceof TypesNode) {
            $this->getCurrentVariableTypeCollector()->add($node);
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
        $variableTypeCollector = $this->getCurrentVariableTypeCollector();

        if (!$variableTypeCollector->isDeclared($name)) {
            return;
        }

        $type = $variableTypeCollector->getDeclaredType($name);

        if (in_array($type, self::UNSUPPORTED_TYPES)) {
            trigger_error(
                sprintf('Invalid dot operation on unsupported type \'%s\' (at %s)', $type, $location),
                E_USER_ERROR
            );
        }

        if (!str_starts_with($type, '\\')) {
            return;
        }

        $rc = new ReflectionClass($type); // ValidTypes already ensure the type is, well, valid.

        // property
        if (
            $rc->hasProperty($attribute) &&
            $rc->getProperty($attribute)->isPublic()
        ) {
            return;
        }

        // dynamic property
        foreach (DocBlockFactory::createInstance()->create($rc)->getTagsWithTypeByName('property') as $tag) {
            if ($attribute === $tag->getVariableName()) {
                return;
            }
        }

        // method (incl. getX, isX hasX short hand forms)
        $ucFirstAttr = ucfirst($attribute);
        foreach (
            [$attribute, "get$ucFirstAttr", "is$ucFirstAttr", "has$ucFirstAttr"]
            as $potentialMethod
        ) {
            if (!$rc->hasMethod($potentialMethod)) {
                continue;
            }

            if ($rc->getMethod($potentialMethod)->isPublic()) {
                return;
            }
            break; // don't try other potential methods
        }

        trigger_error(
            "Invalid attribute '$attribute' for type '$type' (at $location)'",
            E_USER_ERROR
        );
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
