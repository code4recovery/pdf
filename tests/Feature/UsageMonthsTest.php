<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use App\Support\UsageReport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageMonthsTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/usage/months')->assertRedirect('/login');
    }

    #[Test]
    public function months_page_lists_every_month_since_the_first(): void
    {
        $this->travelTo('2026-09-15');

        UsageMonthly::create([
            'month' => '2024-01-01',
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'source_type' => 'json',
            'feed_hash' => '',
            'feed_host' => '',
            'referrer_host' => '',
            'count' => 3,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/usage/months')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UsageMonths')
                ->has('monthly', 33)
                ->where('monthly.32.month', '2024-01')
                ->where('monthly.32.pdfs', 3));
    }

    #[Test]
    public function dashboard_shows_the_last_twelve_months(): void
    {
        $this->travelTo('2026-09-15');

        UsageEvent::factory()->create(['created_at' => '2025-01-10']);

        $this->actingAs(User::factory()->create())
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('monthly', 12)
                ->where('monthly.0.month', '2026-09')
                ->where('monthly.11.month', '2025-10'));
    }

    #[Test]
    public function monthly_without_a_limit_has_no_cutoff(): void
    {
        $this->travelTo('2026-09-15');

        UsageEvent::factory()->create(['created_at' => '2020-03-01']);

        $months = app(UsageReport::class)->monthly(null);

        $this->assertSame('2020-03', end($months)['month']);
        $this->assertSame(1, end($months)['pdfs']);
    }
}
