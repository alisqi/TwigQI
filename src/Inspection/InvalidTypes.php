<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Inspection;

use AlisQI\TwigQI\Helper\NodeLocation;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

class InvalidTypes implements NodeVisitorInterface
{
    private const BASIC_TYPES = [
        'string',
        'number',
        'boolean',
        'null',
        'iterable',
        'object',
        'mixed',
    ];

    private const DEPRECATED_TYPES = [
        'bool'  => 'boolean',
        'int'   => 'number',
        'float' => 'number',
    ];

    private const FQN_REGEX = '/^\\\\[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof TypesNode) {
            $location = new NodeLocation($node);

            foreach ($node->getAttribute('mapping') as $name => ['type' => $type]) {
                $this->validateType($name, $type, $location);
            }
        }

        return $node;
    }

    private function validateType(string $name, string $type, NodeLocation $location): void
    {
        if ($type[0] === '?') {
            $type = substr($type, 1);
        }
        if (str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2);
        }

        if (str_starts_with($type, 'iterable') && $type !== 'iterable') {
            $matches = [];
            if (preg_match('/<(?>(string|number),\s*)?(.+)>/', substr($type, 8), $matches)) {
                [, , $valueType] = $matches;
                $this->validateType($name, $valueType, $location);
                return;
            } // else not valid!
        }

        if (in_array($type, self::BASIC_TYPES)) {
            return;
        }

        if ($replacement = (self::DEPRECATED_TYPES[$type] ?? null)) {
            $this->logger->warning(
                "Deprecated type '$type' used (at $location). Use '$replacement' instead.",
            );
            return;
        }

        if (preg_match_all(self::FQN_REGEX, $type)) {
            if (class_exists($type) || interface_exists($type) || trait_exists($type)) {
                return;
            }
        }

        $this->logger->error(
            "Invalid type '$type' for variable '$name' (at $location)",
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
