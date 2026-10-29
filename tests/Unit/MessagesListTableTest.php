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
        yield 'start after end, start still ahead' => ['2026-11-10', '2026-10-20', 'upcoming'];
    }

    #[DataProvider('schedules')]
    public function test_state_follows_the_schedule(string $start, string $end, string $expected): void
    {
        $this->assertSame($expected, MessagesListTable::state(['date_start' => $start, 'date_end' => $end, 'days' => '']));
    }

    private function renderColumn(string $status, array $meta): string
    {
        $post = $this->message($status);
        $this->meta = $meta;
        Functions\when('get_post')->justReturn($post);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('UTC'));
        Functions\when('wp_date')->alias(static fn(string $format, int $timestamp): string => gmdate('Y-m-d', $timestamp));
        Functions\stubEscapeFunctions();

        ob_start();
        MessagesListTable::render_column('teksttv_status', $post->ID);
        return (string) ob_get_clean();
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function columns(): iterable
    {
        yield 'running: end date' => [['_teksttv_date_start' => '2026-10-20', '_teksttv_date_end' => '2026-11-03'], '<span class="teksttv-planning-state">Actief</span><br /><span class="description">t/m 2026-11-03</span>'];
        yield 'running: open ended' => [['_teksttv_date_start' => '2026-10-20'], '<span class="teksttv-planning-state">Actief</span><br /><span class="description">Zonder einddatum</span>'];
        yield 'upcoming: start date' => [['_teksttv_date_start' => '2026-11-10', '_teksttv_date_end' => '2026-11-20'], '<span class="teksttv-planning-state">Gepland</span><br /><span class="description">Vanaf 2026-11-10</span>'];
        yield 'expired: end date' => [['_teksttv_date_end' => '2026-10-27'], '<span class="teksttv-planning-state">Verlopen</span><br /><span class="description">t/m 2026-10-27</span>'];
        yield 'every day' => [['_teksttv_days' => ['1', '2', '3', '4', '5', '6', '7']], '<span class="teksttv-planning-state">Actief</span><br /><span class="description">Zonder einddatum</span>'];
        // Today is a Wednesday.
        yield 'no days' => [['_teksttv_days' => []], '<span class="teksttv-planning-state">Vandaag niet</span><br /><span class="description">Zonder einddatum</span><br /><span class="description">Geen dagen geselecteerd</span>'];
        yield 'weekend' => [['_teksttv_days' => ['7', '6']], '<span class="teksttv-planning-state">Vandaag niet</span><br /><span class="description">Zonder einddatum</span><br /><span class="description">Alleen Za, Zo</span>'];
        yield 'weekend, upcoming' => [['_teksttv_date_start' => '2026-11-10', '_teksttv_days' => ['6', '7']], '<span class="teksttv-planning-state">Gepland</span><br /><span class="description">Vanaf 2026-11-10</span><br /><span class="description">Alleen Za, Zo</span>'];
        yield 'some days, in weekday order' => [['_teksttv_days' => ['5', '1', '3']], '<span class="teksttv-planning-state">Actief</span><br /><span class="description">Zonder einddatum</span><br /><span class="description">Alleen Ma, Wo, Vr</span>'];
    }

    /** @param array<string, mixed> $meta */
    #[DataProvider('columns')]
    public function test_column_shows_state_then_the_relevant_date_like_the_date_column(array $meta, string $expected): void
    {
        $this->assertSame($expected, $this->renderColumn('publish', $meta));
    }

    /** @return iterable<string, array{string}> */
    public static function unpublishedStatuses(): iterable
    {
        yield 'draft' => ['draft'];
        yield 'scheduled' => ['future'];
        yield 'pending' => ['pending'];
        yield 'private' => ['private'];
    }

    #[DataProvider('unpublishedStatuses')]
    public function test_column_stays_empty_until_the_message_is_published(string $status): void
    {
        $this->assertSame('', $this->renderColumn($status, ['_teksttv_date_start' => '2026-1-5', '_teksttv_date_end' => '2026-11-03']));
    }

    public function test_column_flags_an_invalid_schedule_like_a_missed_schedule(): void
    {
        $this->assertSame(
            '<strong class="error-message">Ongeldige planning</strong>',
            $this->renderColumn('publish', ['_teksttv_date_start' => '2026-1-5'])
        );
    }

    public function test_column_is_hidden_without_scheduling(): void
    {
        $this->features = [];

        $this->assertSame(['title' => 'Titel'], MessagesListTable::add_column(['title' => 'Titel']));
    }

    public function test_meta_queries_compare_against_today(): void
    {
        $this->assertSame('2026-10-28', MessagesListTable::meta_query('upcoming')[0]['value']);
        $this->assertSame('>', MessagesListTable::meta_query('upcoming')[0]['compare']);
        $expired = MessagesListTable::meta_query('expired');
        $this->assertSame(['key' => '_teksttv_date_end', 'value' => '2026-10-28', 'compare' => '<', 'type' => 'DATE'], $expired[1]);
        // A future start wins over a past end, so the views never overlap.
        $this->assertSame(['key' => '_teksttv_date_start', 'value' => '2026-10-28', 'compare' => '<=', 'type' => 'DATE'], $expired[2][2]);
        $this->assertSame('AND', MessagesListTable::meta_query('active')['relation']);
    }

    public function test_column_follows_the_title(): void
    {
        $this->assertSame(
            ['cb' => '', 'title' => 'Titel', 'teksttv_status' => 'Planning', 'date' => 'Datum'],
            MessagesListTable::add_column(['cb' => '', 'title' => 'Titel', 'date' => 'Datum'])
        );
    }

    public function test_views_are_hidden_without_scheduling(): void
    {
        $this->features = [];

        $this->assertSame(['all' => 'Alle'], MessagesListTable::add_views(['all' => 'Alle']));
    }

    /** @return iterable<string, array{bool, bool, string, string, bool}> */
    public static function queries(): iterable
    {
        yield 'main message query' => [true, true, Messages::POST_TYPE, 'upcoming', true];
        // The view counts run their own queries on the same screen.
        yield 'secondary admin query' => [true, false, Messages::POST_TYPE, 'expired', false];
        yield 'front end' => [false, true, Messages::POST_TYPE, 'expired', false];
        yield 'other post type' => [true, true, 'post', 'active', false];
        yield 'unknown state' => [true, true, Messages::POST_TYPE, 'bogus', false];
    }

    #[DataProvider('queries')]
    public function test_filter_only_restricts_the_main_admin_message_query(bool $is_admin, bool $is_main, string $post_type, string $state, bool $filtered): void
    {
        $_GET = ['teksttv_state' => $state];
        Functions\when('is_admin')->justReturn($is_admin);
        $query = Mockery::mock(\WP_Query::class);
        $query->shouldReceive('is_main_query')->andReturn($is_main);
        $query->shouldReceive('get')->with('post_type')->andReturn($post_type);
        if ($filtered) {
            $query->shouldReceive('set')->once()->with('post_status', 'publish');
            $query->shouldReceive('set')->once()->with('meta_query', MessagesListTable::meta_query($state));
        } else {
            $query->shouldNotReceive('set');
        }

        MessagesListTable::filter_query($query);
    }
}
