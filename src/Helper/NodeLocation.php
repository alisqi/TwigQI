<?php

namespace AlisQI\TwigQI\Helper;

use Twig\Node\Node;

class NodeLocation
{
    public function __construct(private readonly Node $node)
    {
    }

    public function __toString(): string
    {
        $sourcePath = ($this->node->getSourceContext() ?? $this->node->getNode('node')->getSourceContext())?->getPath()
            ?? 'unknown';

        return "$sourcePath:{$this->node->getTemplateLine()}";
    }
}