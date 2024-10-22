<?php

declare(strict_types=1);

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

        $compiler->write('// type assertions' . PHP_EOL);

        foreach (
            $this->getNode('types')->getAttribute('mapping')
                as $name => ['type' => $type, 'optional' => $optional]
        ) {
            $this->compileTypeAssertions($compiler, $name, $type, $optional);
        }

        $compiler->write('// end type assertions' . PHP_EOL);
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
        $compiler->write('if (!array_key_exists(')
            ->string($name)
            ->raw(', $context)) {' . PHP_EOL)
            ->indent()
            ->write('trigger_error(sprintf("Non-optional variable \'%s\' is not set", ')
            ->string($name)
            ->raw('), E_USER_ERROR);' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL);
    }

    public function assertVariableIsNotNull(Compiler $compiler, string $name): void
    {
        $compiler
            ->write('if (array_key_exists(')
            ->string($name)
            ->raw(', $context) && is_null($context[')
            ->string($name)
            ->raw('])) {' . PHP_EOL)
            ->indent()
            ->write('trigger_error(sprintf("Non-nullable variable \'%s\' is null", ')
            ->string($name)
            ->raw('), E_USER_ERROR);' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL);
    }

    private function assertType(Compiler $compiler, string $name, string $type): void
    {
        $compiler
            ->write('if (($value = $context[')
            ->string($name)
            ->raw('] ?? null) !== null && !\AlisQI\TwigQI\Assertion\AssertType::matches($value, ')
            ->string($type)
            ->raw(')) {' . PHP_EOL)
            ->indent()
            ->write('trigger_error(sprintf("Type for variable \'%s\' does not match", ')
            ->string($name)
            ->raw('), E_USER_ERROR);' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL)
            ->write('unset($value);' . PHP_EOL);
    }
}
