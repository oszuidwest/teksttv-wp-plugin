<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use TekstTV\Helpers;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Helpers::reset_post_taxonomies_cache();
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
        return $ref->invokeArgs(null, $args);
    }
}
