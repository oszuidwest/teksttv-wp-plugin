<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use TekstTV\Messages;

class MessagesTest extends TestCase
{
    /** @var list<string> */
    private array $features = [];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('get_option')->alias(fn(string $name, mixed $default = false): mixed => match ($name) {
            'teksttv_features' => $this->features,
            'teksttv_channels' => [['slug' => 'tv1', 'label' => 'TV 1'], ['slug' => 'tv2', 'label' => 'TV 2']],
            default => $default,
        });
    }

    public function test_init_registers_the_post_type_on_init(): void
    {
        Actions\expectAdded('init')->once()->with([Messages::class, 'register_post_type']);

        Messages::init();
    }

    public function test_post_type_is_not_registered_while_the_feature_is_off(): void
    {
        Functions\expect('register_post_type')->never();

        Messages::register_post_type();
    }

    public function test_post_type_is_private_and_uses_its_own_capabilities(): void
    {
        $this->features = [Messages::FEATURE];
        Functions\expect('register_post_type')->once()->with(Messages::POST_TYPE, Mockery::on(static function (array $args): bool {
            return $args['public'] === false
                && $args['publicly_queryable'] === false
                && $args['show_in_rest'] === false
                && $args['rewrite'] === false
                && $args['show_ui'] === true
                && $args['capability_type'] === ['teksttv_message', 'teksttv_messages']
                && $args['map_meta_cap'] === true;
        }));

        Messages::register_post_type();
    }

    public function test_grant_capabilities_covers_administrators_and_editors(): void
    {
        $granted = [];
        $role = Mockery::mock();
        $role->shouldReceive('add_cap')->andReturnUsing(static function (string $cap) use (&$granted): void {
            $granted[] = $cap;
        });
        Functions\when('get_role')->alias(static fn(string $name) => match ($name) {
            'administrator', 'editor' => $role,
            default => null,
        });

        Messages::grant_capabilities();

        $this->assertSame(array_merge(Messages::CAPABILITIES, Messages::CAPABILITIES), $granted);
        $this->assertContains('edit_teksttv_messages', Messages::CAPABILITIES);
        $this->assertNotContains('edit_teksttv', Messages::CAPABILITIES);
    }

    public function test_revoke_capabilities_leaves_roles_a_role_plugin_configured(): void
    {
        $roles = [];
        foreach (['administrator', 'editor'] as $name) {
            $role = Mockery::mock();
            $role->shouldReceive('remove_cap')->times(count(Messages::CAPABILITIES));
            $roles[$name] = $role;
        }
        // Roles a role plugin set up, reachable by name and through the role registry.
        foreach (['author', 'teksttv_message_role'] as $name) {
            $role = Mockery::mock();
            $role->shouldNotReceive('remove_cap');
            $roles[$name] = $role;
        }
        Functions\when('get_role')->alias(static fn(string $name) => $roles[$name] ?? null);
        Functions\expect('wp_roles')->never();
        $GLOBALS['wp_roles'] = (object) ['role_objects' => $roles];

        try {
            Messages::revoke_capabilities();
        } finally {
            unset($GLOBALS['wp_roles']);
        }
    }

    public function test_channels_without_selection_air_everywhere(): void
    {
        Functions\when('get_post_meta')->justReturn('');

        $this->assertSame([], Messages::channels(5));
        $this->assertTrue(Messages::airs_on(5, 'tv1'));
        $this->assertTrue(Messages::airs_on(5, 'tv2'));
    }

    public function test_channels_with_selection_air_only_there(): void
    {
        Functions\when('get_post_meta')->justReturn(['tv2', '', ['tv1'], 3]);

        $this->assertSame(['tv2'], Messages::channels(5));
        $this->assertFalse(Messages::airs_on(5, 'tv1'));
        $this->assertTrue(Messages::airs_on(5, 'tv2'));
    }

    public function test_sanitize_channels_keeps_only_configured_slugs(): void
    {
        $this->assertSame(['tv2', 'tv1'], Messages::sanitize_channels(['TV2', 'tv1', 'tv1', 'tv9', ['tv1'], null]));
        $this->assertSame([], Messages::sanitize_channels('tv1'));
    }
}
