<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Helper;

use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\ImportNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

abstract class MacroReferenceVisitor implements NodeVisitorInterface
{
    private bool $currentlyImporting = false;
    
    /** @var string[] */
    private array $importedTemplates = [];
    
    /** @var array<string, MacroNode> */
    protected array $macroNodes = [];

    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->macroNodes += iterator_to_array($node->getNode('macros'));
        }
        
        if ($node instanceof ImportNode && !$this->currentlyImporting) {
            $this->currentlyImporting = true;
            $this->import($env, $node);
            $this->currentlyImporting = false;
        }

        if ($node instanceof MacroReferenceExpression && !$this->currentlyImporting) {
            $this->checkReference($node);
        }

        return $node;
    }

    private function import(Environment $env, ImportNode $node): void
    {
        if (!($expr = $node->getNode('expr')) instanceof ConstantExpression) {
            return;
        }
        
        $templateName = $expr->getAttribute('value');
        if (!in_array($templateName, $this->importedTemplates, true)) {
            $this->importedTemplates[] = $templateName;
            $env->compileSource($env->getLoader()->getSourceContext($templateName));
        }
    }
    
    abstract protected function checkReference(MacroReferenceExpression $node): void;

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode && !$this->currentlyImporting) {
            $this->macroNodes = [];
            $this->importedTemplates = [];
        }
        
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
