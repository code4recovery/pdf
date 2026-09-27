<?php

namespace Tests\Feature;

use App\Models\FeedLabel;
use App\Models\User;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use App\Support\UsageReport;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageDashboardTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/usage')->assertRedirect('/login');
    }

    #[Test]
    public function dashboard_renders_all_sections(): void
    {
        $user = User::factory()->create();

        UsageEvent::factory()->create();

        $this->actingAs($user)
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Usage')
                ->has('monthly')
                ->has('sources')
                ->has('topFeeds')
                ->has('topReferrers')
                ->has('outcomes')
                ->has('settings')
                ->has('heaviest')
            );
    }

    #[Test]
    public function monthly_merges_rolled_up_and_raw_counts(): void
    {
        UsageMonthly::create([
            'month' => '2026-09-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => '',
            'feed_host' => '',
            'referrer_host' => '',
            'count' => 5,
        ]);

        $this->travelTo(Carbon::parse('2026-09-15'));
        UsageEvent::factory()->count(2)->create([
            'event' => 'pdf_generated',
            'outcome' => 'success',
        ]);

        // monthly()'s 24-month cutoff is measured from now(), so it must be
        // read while time travel still puts "now" in 2026-09 — otherwise the
        // fixed 2026-09 fixture dates eventually fall outside the window.
        $months = collect((new UsageReport())->monthly())->keyBy('month');

        $this->travelBack();

        $this->assertSame(7, $months['2026-09']['pdfs']);
    }

    #[Test]
    public function labels_replace_hosts_in_top_feeds(): void
    {
        $hash = str_repeat('a', 64);

        UsageEvent::factory()->create([
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'feed_hash' => $hash,
            'feed_host' => 'example.org',
        ]);

        FeedLabel::create(['feed_hash' => $hash, 'label' => 'Example Intergroup']);

        $feed = collect((new UsageReport())->topFeeds())
            ->firstWhere('fingerprint', substr($hash, 0, 12));

        $this->assertSame('Example Intergroup', $feed['label']);
    }

    #[Test]
    public function settings_are_counted(): void
    {
        $settingsFor = fn (string $groupBy): array => [
            'group_by' => $groupBy,
            'language' => 'en',
            'paper' => '4.25x11',
            'font' => 'serif',
            'font_size' => 12,
            'mode' => 'download',
            'numbering' => true,
            'regions_selected' => false,
            'front_cover' => false,
            'back_cover' => false,
            'options' => ['pagebreaks'],
        ];

        UsageEvent::factory()->count(3)->create([
            'event' => 'pdf_generated',
            'settings' => $settingsFor('region-day'),
        ]);
        UsageEvent::factory()->create([
            'event' => 'pdf_generated',
            'settings' => $settingsFor('day'),
        ]);

        $groupBySettings = collect((new UsageReport())->settings())
            ->where('setting', 'group_by')
            ->keyBy('value');

        $this->assertSame(3, $groupBySettings['region-day']['count']);
        $this->assertSame(1, $groupBySettings['day']['count']);
    }

    #[Test]
    public function failures_are_counted_by_outcome(): void
    {
        UsageEvent::factory()->count(3)->create([
            'event' => 'pdf_generated',
            'outcome' => 'fetch_failed',
            'upstream_status' => 404,
        ]);

        $outcome = collect((new UsageReport())->outcomes())
            ->first(fn (array $row): bool => $row['outcome'] === 'fetch_failed' && $row['upstream_status'] === 404);

        $this->assertNotNull($outcome);
        $this->assertSame(3, $outcome['count']);
    }
}
