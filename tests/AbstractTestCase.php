<?php

namespace AlisQI\TwigStan\Tests;

use AlisQI\TwigStan\Extension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

abstract class AbstractTestCase extends TestCase
{
    protected Environment $env;
    protected array $errors;
    
    public function setUp(): void
    {
        $this->env = new Environment(new ArrayLoader());
        $this->env->addExtension(new Extension());
        
        $this->errors = [];
        set_error_handler(function (int $code, string $error) {
           $this->errors[] = $error;
        });
    }

    public function tearDown(): void
    {
        restore_error_handler();
    }
}
