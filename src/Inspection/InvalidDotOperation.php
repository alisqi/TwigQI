<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use AlisQI\TwigQI\Helper\VariableTypeCollector;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use Twig\Environment;
use Twig\Node\Expression\ArrowFunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ForNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
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
            $node instanceof ArrowFunctionExpression ||
            $node instanceof ForNode ||
            $node instanceof SetNode
        ) {
            foreach ($this->extractScopedVariableTypes($node) as $name => $type) {
                $this->getCurrentVariableTypeCollector()->push($name, $type);
            }
        }

        if (
            $node instanceof GetAttrExpression &&
            $node->getAttribute('type') !== Template::ARRAY_CALL &&
            ($nameNode = $node->getNode('node')) instanceof ContextVariable
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

        if (str_starts_with($type, '?')) {
            $type = substr($type, 1);
        }
        
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
        if (false !== $docBlock = $rc->getDocComment()) {
            foreach (DocBlockFactory::createInstance()->create($docBlock)->getTagsWithTypeByName('property') as $tag) {
                if ($attribute === $tag->getVariableName()) {
                    return;
                }
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
        if (
            $node instanceof ArrowFunctionExpression ||
            $node instanceof ForNode
        ) {
            foreach (array_keys($this->extractScopedVariableTypes($node)) as $name) {
                $this->getCurrentVariableTypeCollector()->pop($name);
            }
        }

        return $node;
    }

    /** @return iterable<string, string> */
    private function extractScopedVariableTypes(ArrowFunctionExpression|ForNode|SetNode $node): array
    {
        if (
            $node instanceof ArrowFunctionExpression ||
            $node instanceof SetNode
        ) {
            $names = $node->getNode('names');

            if (!$names->hasNode('0')) { // skip {% apply %} (see ApplyTokenParser)
                return [];
            }

            $variables = [
                $names->getNode('0')->getAttribute('name') => 'mixed'
            ];
            if ($names->hasNode('1')) {
                $variables[$names->getNode('1')->getAttribute('name')] = 'mixed';
            }
            return $variables;
        }

        if ($node instanceof ForNode) {
            $keyName   = $node->getNode('key_target')  ->getAttribute('name');
            $valueName = $node->getNode('value_target')->getAttribute('name');

            $keyType = $valueType = 'mixed';

            if (($seqNode = $node->getNode('seq')) instanceof ContextVariable) {
                $seqName = $seqNode->getAttribute('name');

                if ((null !== $seqType = $this->getCurrentVariableTypeCollector()->getDeclaredType($seqName))) {
                    if (str_ends_with($seqType, '[]')) {
                        $seqType = 'iterable<' . substr($seqType, 0, -2) . '>';
                    }
                    if (str_starts_with($seqType, 'iterable<')) {
                        $matches = [];
                        preg_match('/<((string|number),\s*)?(.+)>/', substr($seqType, 8), $matches);
                        [, , $declaredKeyType, $declaredValueType] = $matches;

                        if ($declaredKeyType !== '') {
                            $keyType = $declaredKeyType;
                        }
                        $valueType = $declaredValueType;
                    }
                }
            }

            return [
                $keyName   => $keyType,
                $valueName => $valueType,
            ];
        }
    }

    public function getPriority(): int
    {
        return 0;
    }


}
