<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use App\Support\UsageReport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageReferrersTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/usage/referrers')->assertRedirect('/login');
    }

    #[Test]
    public function referrers_page_lists_every_referrer(): void
    {
        foreach (range(1, 20) as $i) {
            UsageEvent::factory()->create(['referrer_host' => "site{$i}.org"]);
        }

        $this->actingAs(User::factory()->create())
            ->get('/usage/referrers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UsageReferrers')->has('referrers', 20));
    }

    #[Test]
    public function referrers_are_alphabetical_and_include_rolled_up_months(): void
    {
        UsageEvent::factory()->create(['referrer_host' => 'zebra.org']);
        UsageEvent::factory()->create(['event' => 'form_opened', 'referrer_host' => 'Apple.org']);
        UsageMonthly::create([
            'month' => '2026-01-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'tsml',
            'feed_hash' => '',
            'feed_host' => '',
            'referrer_host' => 'mango.org',
            'count' => 5,
        ]);

        $referrers = app(UsageReport::class)->referrers();

        $this->assertSame(['Apple.org', 'mango.org', 'zebra.org'], array_column($referrers, 'host'));
        $this->assertSame(5, $referrers[1]['pdfs']);
        $this->assertSame(1, $referrers[0]['forms_opened']);
    }

    #[Test]
    public function top_referrers_still_limits_to_fifteen(): void
    {
        foreach (range(1, 20) as $i) {
            UsageEvent::factory()->create(['referrer_host' => "site{$i}.org"]);
        }

        $this->assertCount(15, app(UsageReport::class)->topReferrers());
    }

    #[Test]
    public function each_referrer_lists_the_feeds_it_sent(): void
    {
        $tsml = hash('sha256', 'tsml-feed');
        $sheet = hash('sha256', 'sheet-feed');

        UsageEvent::factory()->count(2)->create(['referrer_host' => 'area.org', 'feed_hash' => $tsml, 'feed_host' => 'area.org', 'feed_url' => 'https://area.org/feed', 'source_type' => 'tsml']);
        UsageEvent::factory()->create(['referrer_host' => 'area.org', 'event' => 'form_opened', 'feed_hash' => $sheet, 'feed_host' => 'docs.google.com', 'feed_url' => null, 'source_type' => 'google_sheet']);
        UsageMonthly::create([
            'month' => '2026-01-01', 'event' => 'pdf_generated', 'outcome' => 'success', 'source_type' => 'tsml',
            'feed_hash' => $tsml, 'feed_host' => 'area.org', 'referrer_host' => 'area.org', 'count' => 4,
        ]);
        UsageEvent::factory()->create(['referrer_host' => 'other.org', 'feed_hash' => $sheet]);

        $area = collect(app(UsageReport::class)->referrers())->firstWhere('host', 'area.org');

        $this->assertSame([
            ['fingerprint' => substr($tsml, 0, 12), 'label' => null, 'host' => 'area.org', 'url' => 'https://area.org/feed', 'source_type' => 'tsml', 'forms_opened' => 0, 'pdfs' => 6],
            ['fingerprint' => substr($sheet, 0, 12), 'label' => null, 'host' => 'docs.google.com', 'url' => null, 'source_type' => 'google_sheet', 'forms_opened' => 1, 'pdfs' => 0],
        ], $area['feeds']);
    }
}
