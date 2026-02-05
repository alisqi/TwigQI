<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionNode\EnumFunction;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class InvalidEnumCase implements NodeVisitorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (
            $node instanceof GetAttrExpression &&
            $node->getNode('node') instanceof EnumFunction &&
            $node->getNode('attribute') instanceof ConstantExpression
        ) {
            $this->checkArguments($node);
        }

        return $node;
    }

    private function checkArguments(GetAttrExpression $node): void
    {
        $location = new NodeLocation($node);

        // No need to test whether enum_exists, as Twig's EnumFunction does that already.
        $case = $node->getNode('attribute')->getAttribute('value');
        if ($case === 'cases') {
            return;
        }

        $enumClass = $node->getNode('node')->getNode('arguments')->getNode('0')->getAttribute('value');
        foreach ($enumClass::cases() as $enumCase) {
            if ($enumCase->name === $case) {
                return;
            }
        }

        $this->logger->error("Invalid enum case '$case' (at $location)");
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
