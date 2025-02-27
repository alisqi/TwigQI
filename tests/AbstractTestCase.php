<?php

declare(strict_types=1);

namespace AlisQI\TwigQI\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;

abstract class AbstractTestCase extends TestCase
{
    protected Environment $env;
    protected array $errors;
    
    /** @var array<string, string> */
    private static array $extensionPerTestCase = [];

    /**
     * To test inspections in isolation, we need to create Twig Environments
     * with Extensions that load only one inspection (NodeVisitor) at a time.
     * 
     * When Twig compiles templates to Template classes, their class name contains
     * the Environment's optionsHash to make them unique.
     * 
     * _However_, that options hash includes only the Extensions' class names.
     * Adding the same Extension class but with different inspections (node visitors)
     * causes hash collision (for lack of a more precise term), meaning that templates
     * compiled with a different inspection get reused.
     * 
     * Therefore, every TestCase must create a unique extension class.
     * The easiest way is to create anonymous classes that extend AlisQI\TwigQI\Extension.
     * We can't do that in this class because the name of the anonymous class is based on
     * the class that creates it, which again leads to collisions in the options hash.
     */
    abstract protected function createUniqueExtensionClass(): ExtensionInterface;
    
    public function setUp(): void
    {
        $this->env = new Environment(new ArrayLoader());

        $extension = $this->createUniqueExtensionClass();
        $this->env->addExtension($extension);

        // Ensure Extension class is unique per TestCase
        $extensionClass = get_class($extension);
        if (!array_key_exists(static::class, self::$extensionPerTestCase)) {
            if (in_array($extensionClass, self::$extensionPerTestCase)) {
                throw new \RuntimeException('Duplicate extension class name');
            }
            
            self::$extensionPerTestCase[static::class] = $extensionClass;
        }
        
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
