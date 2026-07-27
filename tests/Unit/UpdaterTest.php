<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use TekstTV\Updater;

class UpdaterTest extends TestCase
{
    // =========================================================================
    // should_check_for_updates() - request-context gate
    // =========================================================================

    public function test_gate_allows_admin_requests(): void
    {
        Functions\expect('is_admin')->once()->andReturn(true);
        Functions\expect('wp_doing_cron')->never();

        $this->assertTrue(Updater::should_check_for_updates());
    }

    public function test_gate_allows_cron_requests(): void
    {
        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(true);

        $this->assertTrue(Updater::should_check_for_updates());
    }

    public function test_gate_blocks_frontend_requests(): void
    {
        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);

        $this->assertFalse(Updater::should_check_for_updates());
    }

    // =========================================================================
    // init() - early return before the update checker is constructed
    // =========================================================================

    public function test_init_skips_on_frontend_requests(): void
    {
        Functions\expect('is_admin')->once()->andReturn(false);
        Functions\expect('wp_doing_cron')->once()->andReturn(false);

        // If the gate regressed, PucFactory::buildUpdateChecker() would call
        // WordPress functions that are undefined here and error out.
        Updater::init('/path/to/teksttv.php');

        $this->assertTrue(true);
    }
}
