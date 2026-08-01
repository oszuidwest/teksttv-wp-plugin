<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use TekstTV\BlockRegistry;
use TekstTV\Helpers;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $registry_types = new \ReflectionProperty(BlockRegistry::class, 'types');
        $registry_types->setValue(null, []);
        $ai_cache = new \ReflectionProperty(Helpers::class, 'ai_supported_cache');
        $ai_cache->setValue(null, null);
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

    /**
     * Stub post with enough body text to clear the AI minimum-input check.
     *
     * @param array<string, mixed> $overrides
     */
    protected static function makePost(array $overrides = []): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = 42;
        $post->post_title = $overrides['post_title'] ?? 'Titel';
        $post->post_content = $overrides['post_content'] ?? '<p>' . implode(' ', array_fill(0, 60, 'woord')) . '</p>';
        return $post;
    }

    /**
     * Build the fluent WordPress AI prompt mock with one response per call.
     */
    protected static function mockAiBuilder(string|\WP_Error ...$responses): \Mockery\MockInterface
    {
        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('is_supported_for_text_generation')->zeroOrMoreTimes()->andReturn(true);
        $builder->shouldReceive('generate_text')
            ->times(count($responses))
            ->andReturn(...$responses);

        return $builder;
    }

    /**
     * Build the fluent WordPress AI prompt mock whose capability probe
     * reports that no provider matches the configured requirements.
     */
    protected static function mockUnsupportedAiBuilder(): \Mockery\MockInterface
    {
        $builder = \Mockery::mock();
        $builder->shouldReceive('using_system_instruction')->andReturnSelf();
        $builder->shouldReceive('using_max_tokens')->andReturnSelf();
        $builder->shouldReceive('is_supported_for_text_generation')->once()->andReturn(false);

        return $builder;
    }
}
