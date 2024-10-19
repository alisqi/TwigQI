<?php

namespace AlisQI\TwigQI\Assertion;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\TypesNode;

#[YieldReady]
final class AssertedTypesNode extends Node
{
    public function __construct(TypesNode $types)
    {
        parent::__construct(['types' => $types]);
    }

    public function compile(Compiler $compiler): void
    {
        parent::compile($compiler); // compile the original TypesNode (which doesn't do anything)

        foreach ($this->getNode('types')->getAttribute('mapping') as $name => ['type' => $type]) {
            $this->compileTypeAssertions($compiler, $name, $type);
        }
    }

    private function compileTypeAssertions(Compiler $compiler, string $name, string $type): void
    {
        $isNullable = false;
        if (str_starts_with($type, '?')) {
            $isNullable = true;
            $type = substr($type, 1);
        }

        if (!$isNullable) {
            $compiler->raw('if (is_null($context[')
                ->string($name)
                ->raw('])) {')
                ->indent()
                ->write('trigger_error("Non-nullable variable \'$name\' is null", E_USER_ERROR);')
                ->outdent()
                ->write('}');
        }
    }
}
