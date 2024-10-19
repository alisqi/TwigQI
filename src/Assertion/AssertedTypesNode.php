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

        foreach (
            $this->getNode('types')->getAttribute(
                'mapping'
            ) as $name => ['type' => $type, 'optional' => $optional]
        ) {
            $this->compileTypeAssertions($compiler, $name, $type, $optional);
        }
    }

    private function compileTypeAssertions(Compiler $compiler, string $name, string $type, bool $optional): void
    {
        $isNullable = false;
        if (str_starts_with($type, '?')) {
            $isNullable = true;
            $type = substr($type, 1);
        }

        if (!$optional) {
            $this->assertVariableExists($compiler, $name);
        }

        if (!$isNullable) {
            $this->assertVariableIsNotNull($compiler, $name);
        }

        $this->assertType($compiler, $name, $type);
    }

    public function assertVariableExists(Compiler $compiler, string $name): void
    {
        $compiler->raw('if (!array_key_exists(')
            ->string($name)
            ->raw(', $context)) {')
            ->indent()
            ->write('trigger_error("Non-optional variable \'$name\' is not set", E_USER_ERROR);')
            ->outdent()
            ->write('}');
    }

    public function assertVariableIsNotNull(Compiler $compiler, string $name): void
    {
        $compiler->raw('if (array_key_exists(')
            ->string($name)
            ->raw(', $context) && is_null($context[')
            ->string($name)
            ->raw('])) {')
            ->indent()
            ->write('trigger_error("Non-nullable variable \'$name\' is null", E_USER_ERROR);')
            ->outdent()
            ->write('}');
    }

    private function assertType(Compiler $compiler, string $name, string $type): void
    {
        $functions = match ($type) {
            'string' => 'is_string',
            'number' => ['is_int', 'is_float'],
            'boolean' => 'is_bool',
            'object' => 'is_object',
            // no point in asserting 'mixed'
            default => null,
        };

        if ($functions === null) {
            return;
        }

        if (!is_array($functions)) {
            $functions = [$functions];
        }

        $compiler->raw('if (($value = $context[')
            ->string($name)
            ->raw("] ?? null) !== null && !(");

        $first = true;
        foreach ($functions as $fn) {
            if (!$first) {
                $compiler->raw(' || ');
            }
            $first = false;

            $compiler->raw("$fn(\$value)");
        }

        $compiler->raw(')) {')
            ->indent()
            ->write('trigger_error("Type for variable \'$name\' does not match", E_USER_ERROR);')
            ->outdent()
            ->write('}')
            ->write('unset($value);');
    }

}
