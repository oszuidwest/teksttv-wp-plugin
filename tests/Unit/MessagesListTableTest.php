<?php

namespace TekstTV\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use TekstTV\Messages;
use TekstTV\MessagesListTable;

class MessagesListTableTest extends TestCase
{
    /** @var list<string> */
    private array $features = ['scheduling'];

    /** @var array<string, mixed> */
    private array $meta = [];

    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        unset($GLOBALS['teksttv_test_found_posts']);
        Functions\when('get_option')->alias(fn(string $name, mixed $default = false): mixed => $name === 'teksttv_features' ? $this->features : $default);
        Functions\when('get_post_meta')->alias(fn($id, $key) => $this->meta[$key] ?? '');
        Functions\when('current_datetime')->justReturn(new \DateTimeImmutable('2026-10-28 12:00:00'));
    }

    private function message(string $status = 'publish'): \WP_Post
    {
        $post = self::makePost();
        $post->post_type = Messages::POST_TYPE;
        $post->post_status = $status;
        return $post;
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function schedules(): iterable
    {
        yield 'open ended' => ['', '', 'active'];
        yield 'running, end inclusive' => ['2026-10-20', '2026-10-28', 'active'];
        yield 'starts today' => ['2026-10-28', '', 'active'];
        yield 'starts tomorrow' => ['2026-10-29', '2026-11-05', 'upcoming'];
        yield 'ended yesterday' => ['2026-10-20', '2026-10-27', 'expired'];
        yield 'start after end, start still ahead' => ['2026-11-10', '2026-11-01', 'upcoming'];
        yield 'malformed date' => ['2026-1-5', '', 'invalid'];
    }

    #[DataProvider('schedules')]
    public function test_state_follows_the_schedule(string $start, string $end, string $expected): void
    {
        $this->meta = ['_teksttv_date_start' => $start, '_teksttv_date_end' => $end];

        $this->assertSame($expected, MessagesListTable::state($this->message()));
    }

    public function test_unpublished_messages_are_never_on_air(): void
    {
        $this->assertSame('unpublished', MessagesListTable::state($this->message('draft')));
    }

    public function test_without_scheduling_every_published_message_is_active(): void
    {
        $this->features = [];
        $this->meta = ['_teksttv_date_end' => '2020-01-01'];

        $this->assertSame('active', MessagesListTable::state($this->message()));
    }

    public function test_meta_queries_compare_against_today(): void
    {
        $this->assertSame('2026-10-28', MessagesListTable::meta_query('upcoming')[0]['value']);
        $this->assertSame('>', MessagesListTable::meta_query('upcoming')[0]['compare']);
        $expired = MessagesListTable::meta_query('expired');
        $this->assertSame(['key' => '_teksttv_date_end', 'value' => '2026-10-28', 'compare' => '<', 'type' => 'DATE'], $expired[1]);
        // Inverted schedules count as upcoming only, so the views never overlap.
        $this->assertSame(['key' => '_teksttv_date_start', 'value' => '2026-10-28', 'compare' => '<=', 'type' => 'DATE'], $expired[2][2]);
        $this->assertSame('AND', MessagesListTable::meta_query('active')['relation']);
    }

    public function test_column_follows_the_title(): void
    {
        $this->assertSame(
            ['cb' => '', 'title' => 'Titel', 'teksttv_status' => 'Tekst TV', 'date' => 'Datum'],
            MessagesListTable::add_column(['cb' => '', 'title' => 'Titel', 'date' => 'Datum'])
        );
    }

    public function test_column_shows_state_period_and_weekdays(): void
    {
        $post = $this->message();
        $this->meta = ['_teksttv_date_start' => '2026-10-20', '_teksttv_date_end' => '2026-11-03', '_teksttv_days' => ['1', '3']];
        Functions\when('get_post')->justReturn($post);
        Functions\when('date_i18n')->alias(static fn(string $format, int $timestamp): string => gmdate('Y-m-d', $timestamp));
        Functions\stubEscapeFunctions();

        ob_start();
        MessagesListTable::render_column('teksttv_status', $post->ID);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('teksttv-message-state--active">Op Tekst TV</span>', $html);
        $this->assertStringContainsString('2026-10-20 t/m 2026-11-03 · Alleen Ma, Wo', $html);
    }

    public function test_views_are_added_with_counts_and_mark_the_current_filter(): void
    {
        $_GET = ['teksttv_state' => 'expired'];
        Functions\when('admin_url')->alias(static fn(string $path): string => 'https://example.test/wp-admin/' . $path);
        Functions\when('add_query_arg')->alias(static fn(array $args, string $url): string => $url . '?' . http_build_query($args));
        Functions\stubEscapeFunctions();
        $GLOBALS['teksttv_test_found_posts'] = 4;

        $views = MessagesListTable::add_views(['all' => 'Alle']);

        $this->assertSame(['all', 'teksttv_state_active', 'teksttv_state_upcoming', 'teksttv_state_expired'], array_keys($views));
        $this->assertStringContainsString('Verlopen <span class="count">(4)</span>', $views['teksttv_state_expired']);
        $this->assertStringContainsString('class="current"', $views['teksttv_state_expired']);
        $this->assertStringNotContainsString('class="current"', $views['teksttv_state_active']);
        $this->assertStringContainsString('teksttv_state=upcoming', $views['teksttv_state_upcoming']);
    }

    public function test_views_are_hidden_without_scheduling(): void
    {
        $this->features = [];

        $this->assertSame(['all' => 'Alle'], MessagesListTable::add_views(['all' => 'Alle']));
    }

    public function test_filter_restricts_the_main_message_query(): void
    {
        $_GET = ['teksttv_state' => 'upcoming'];
        Functions\when('is_admin')->justReturn(true);
        $query = Mockery::mock(\WP_Query::class);
        $query->shouldReceive('is_main_query')->andReturn(true);
        $query->shouldReceive('get')->with('post_type')->andReturn(Messages::POST_TYPE);
        $query->shouldReceive('set')->once()->with('post_status', 'publish');
        $query->shouldReceive('set')->once()->with('meta_query', MessagesListTable::meta_query('upcoming'));

        MessagesListTable::filter_query($query);
    }

    public function test_filter_ignores_unknown_states_and_other_post_types(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $_GET = ['teksttv_state' => 'bogus'];
        $query = Mockery::mock(\WP_Query::class);
        $query->shouldReceive('is_main_query')->andReturn(true);
        $query->shouldReceive('get')->with('post_type')->andReturn(Messages::POST_TYPE, 'post');
        $query->shouldNotReceive('set');

        MessagesListTable::filter_query($query);
        $_GET = ['teksttv_state' => 'active'];
        MessagesListTable::filter_query($query);
    }
}
