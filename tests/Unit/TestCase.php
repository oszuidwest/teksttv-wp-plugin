<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Call a private/protected static method via reflection.
     *
     * @param class-string $class
     * @param list<mixed> $args
     */
    protected static function callPrivate(string $class, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }
}
