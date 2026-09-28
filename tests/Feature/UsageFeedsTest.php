<?php

namespace Tests\Feature;

use App\Models\FeedLabel;
use App\Models\User;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use App\Support\UsageReport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageFeedsTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/usage/feeds')->assertRedirect('/login');
    }

    #[Test]
    public function feeds_page_renders_every_feed(): void
    {
        $user = User::factory()->create();

        UsageEvent::factory()->count(30)->create();

        $this->actingAs($user)
            ->get('/usage/feeds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UsageFeeds')->has('feeds', 30));
    }

    #[Test]
    public function feeds_are_sorted_alphabetically_by_label_then_host(): void
    {
        $zebra = hash('sha256', 'zebra');
        $apple = hash('sha256', 'apple');
        $labelled = hash('sha256', 'labelled');

        UsageEvent::factory()->create(['feed_hash' => $zebra, 'feed_host' => 'zebra.org']);
        UsageEvent::factory()->create(['feed_hash' => $apple, 'feed_host' => 'Apple.org']);
        UsageEvent::factory()->create(['feed_hash' => $labelled, 'feed_host' => 'zzz.org']);
        FeedLabel::create(['feed_hash' => $labelled, 'label' => 'Maine Intergroup']);

        $names = array_map(
            fn (array $feed): string => $feed['label'] ?? $feed['host'],
            app(UsageReport::class)->feeds(),
        );

        $this->assertSame(['Apple.org', 'Maine Intergroup', 'zebra.org'], $names);
    }

    #[Test]
    public function feeds_include_rolled_up_months(): void
    {
        $hash = hash('sha256', 'old-feed');

        UsageMonthly::create([
            'month' => '2026-01-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'tsml',
            'feed_hash' => $hash,
            'feed_host' => 'old.org',
            'referrer_host' => '',
            'count' => 4,
        ]);

        $feeds = app(UsageReport::class)->feeds();

        $this->assertCount(1, $feeds);
        $this->assertSame('old.org', $feeds[0]['host']);
        $this->assertSame(4, $feeds[0]['pdfs']);
        $this->assertSame(substr($hash, 0, 12), $feeds[0]['fingerprint']);
    }

    #[Test]
    public function feeds_show_the_meeting_count_from_their_latest_pdf(): void
    {
        $hash = hash('sha256', 'growing');
        $old = hash('sha256', 'rolled-up-only');

        UsageEvent::factory()->create(['feed_hash' => $hash, 'meeting_count' => 300, 'created_at' => now()->subDays(5)]);
        UsageEvent::factory()->create(['feed_hash' => $hash, 'meeting_count' => 320, 'created_at' => now()->subDay()]);
        UsageEvent::factory()->create(['feed_hash' => $hash, 'meeting_count' => 310, 'created_at' => now()->subDays(3)]);

        UsageMonthly::create([
            'month' => '2026-01-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => $old,
            'feed_host' => 'old.org',
            'referrer_host' => '',
            'count' => 2,
        ]);

        $meetings = array_column(app(UsageReport::class)->feeds(), 'meetings', 'fingerprint');

        $this->assertSame(320, $meetings[substr($hash, 0, 12)]);
        $this->assertNull($meetings[substr($old, 0, 12)]);
    }

    #[Test]
    public function feeds_carry_their_address_from_either_table(): void
    {
        $recent = hash('sha256', 'recent');
        $old = hash('sha256', 'old');

        UsageEvent::factory()->create(['feed_hash' => $recent, 'feed_url' => 'https://recent.org/feed']);
        UsageMonthly::create([
            'month' => '2026-01-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => $old,
            'feed_host' => 'old.org',
            'referrer_host' => '',
            'feed_url' => 'https://old.org/feed',
            'count' => 1,
        ]);

        $urls = array_column(app(UsageReport::class)->feeds(), 'url', 'fingerprint');

        $this->assertSame('https://recent.org/feed', $urls[substr($recent, 0, 12)]);
        $this->assertSame('https://old.org/feed', $urls[substr($old, 0, 12)]);
    }

    #[Test]
    public function monthly_fills_quiet_months_from_first_activity_to_now(): void
    {
        $this->travelTo('2026-09-15');

        UsageEvent::factory()->create(['created_at' => '2026-06-10']);
        UsageEvent::factory()->create(['created_at' => '2026-09-01']);

        $months = array_column(app(UsageReport::class)->monthly(), 'pdfs', 'month');

        $this->assertSame(['2026-09' => 1, '2026-08' => 0, '2026-07' => 0, '2026-06' => 1], $months);
    }

    #[Test]
    public function monthly_is_empty_with_no_activity(): void
    {
        $this->assertSame([], app(UsageReport::class)->monthly());
    }

    #[Test]
    public function feeds_carry_twelve_months_of_pdf_counts_oldest_first(): void
    {
        $this->travelTo('2026-09-15');

        $hash = hash('sha256', 'trend');

        UsageMonthly::create([
            'month' => '2025-10-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => $hash,
            'feed_host' => 'trend.org',
            'referrer_host' => '',
            'count' => 4,
        ]);
        UsageMonthly::create([
            'month' => '2025-09-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => $hash,
            'feed_host' => 'trend.org',
            'referrer_host' => '',
            'count' => 9,
        ]);
        UsageEvent::factory()->count(2)->create(['feed_hash' => $hash, 'created_at' => '2026-09-02']);
        UsageEvent::factory()->create(['feed_hash' => $hash, 'created_at' => '2026-07-20']);
        UsageEvent::factory()->create(['feed_hash' => $hash, 'created_at' => '2026-07-21', 'outcome' => 'fetch_failed']);

        $feed = app(UsageReport::class)->feeds()[0];

        $this->assertSame([4, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 2], $feed['trend']);
        $this->assertSame(16, $feed['pdfs']);
    }

    #[Test]
    public function top_feeds_break_ties_by_most_recent_use(): void
    {
        UsageEvent::factory()->create(['feed_hash' => hash('sha256', 'june'), 'feed_host' => 'june.org', 'created_at' => now()->subDays(80)]);
        UsageEvent::factory()->create(['feed_hash' => hash('sha256', 'this-week'), 'feed_host' => 'this-week.org', 'created_at' => now()->subDays(2)]);
        UsageEvent::factory()->create(['feed_hash' => hash('sha256', 'july'), 'feed_host' => 'july.org', 'created_at' => now()->subDays(50)]);

        $hosts = array_column(app(UsageReport::class)->topFeeds(), 'host');

        $this->assertSame(['this-week.org', 'july.org', 'june.org'], $hosts);
    }

    #[Test]
    public function top_feeds_still_limits_and_orders_by_pdfs(): void
    {
        $busy = hash('sha256', 'busy');

        UsageEvent::factory()->count(3)->create(['feed_hash' => $busy, 'feed_host' => 'busy.org']);
        UsageEvent::factory()->count(30)->create();

        $top = app(UsageReport::class)->topFeeds();

        $this->assertCount(25, $top);
        $this->assertSame('busy.org', $top[0]['host']);
        $this->assertSame(3, $top[0]['pdfs']);
    }
}
