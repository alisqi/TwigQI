<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\Test\ConstantTest;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class InvalidConstant implements NodeVisitorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (
            ($node instanceof FunctionExpression && $node->getAttribute('name') === 'constant') ||
            $node instanceof ConstantTest
        ) {
            $this->checkArguments($node);
        }

        return $node;
    }

    private function checkArguments(FunctionExpression|ConstantTest $node): void
    {
        $arguments = $node->getNode('arguments');

        // ignore `constant('foo') is defined`
        if (
            $node instanceof FunctionExpression &&
            $node->getAttribute('is_defined_test')
        ) {
            return;
        }

        $location = new NodeLocation($node);

        if (count($arguments) === 1) {
            $error = $this->checkConstant($arguments->getNode('0'));
        } else {
            if (count($arguments) === 2) {
                $error = $this->checkConstantAndObject($arguments->getNode('0'), $arguments->getNode('1'));
            } else {
                $error = 'too many arguments';
            }
        }

        if ($error) {
            $this->logger->error("Invalid constant() call: $error (at $location)");
        }
    }

    private function checkConstant(Node $node): ?string
    {
        if (!$node instanceof ConstantExpression) {
            return 'single argument must be string';
        }

        $value = $node->getAttribute('value');

        if (!is_string($value) || !defined($value)) {
            return "invalid constant: '$value'";
        }

        return null;
    }

    private function checkConstantAndObject(Node $constant, Node $name): ?string
    {
        if (
            (!$constant instanceof ConstantExpression) ||
            !is_string($constant->getAttribute('value'))
        ) {
            return 'first argument must be string';
        }

        if (!$name instanceof ContextVariable) {
            return 'second argument must be a variable name';
        }

        return null;
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
