<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests\Type;

class Dummy
{
    public string $pubProp;

    protected string $protProp;

    private string $privProp;

    public function pubMeth(): string {
        return 'publicMethod';
    }

    protected function protMeth(): string {
        return 'protectedMethod';
    }

    private function privMeth(): string {
        return 'privateMethod';
    }

    public function getGit(): bool {
        return true;
    }

    public function isIz(): bool {
        return true;
    }

    public function hasHaz(): bool {
        return true;
    }
}
