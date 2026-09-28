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
