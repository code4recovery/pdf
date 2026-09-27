<?php

namespace Tests\Feature;

use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RollupUsageTest extends TestCase
{
    #[Test]
    public function old_events_move_to_monthly_and_new_ones_stay(): void
    {
        $now = now();

        $this->travelTo($now->copy()->subDays(100));
        UsageEvent::factory()->count(3)->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
            'referrer_host' => null,
        ]);

        $this->travelTo($now->copy()->subDays(10));
        UsageEvent::factory()->count(2)->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
            'referrer_host' => null,
        ]);

        $this->travelBack();

        $this->artisan('usage:rollup')->assertExitCode(0);

        $this->assertSame(2, UsageEvent::count());
        $this->assertSame(1, UsageMonthly::count());
        $this->assertSame(3, UsageMonthly::first()->count);
    }

    #[Test]
    public function running_twice_does_not_double_count(): void
    {
        $this->travelTo(now()->subDays(100));
        UsageEvent::factory()->count(3)->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
            'referrer_host' => null,
        ]);

        $this->travelBack();

        $this->artisan('usage:rollup')->assertExitCode(0);
        $this->artisan('usage:rollup')->assertExitCode(0);

        $this->assertSame(1, UsageMonthly::count());
        $this->assertSame(3, UsageMonthly::first()->count);
    }

    #[Test]
    public function events_in_different_months_get_separate_rows(): void
    {
        $this->travelTo(Carbon::parse('2026-05-31 23:00:00'));
        UsageEvent::factory()->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
        ]);

        $this->travelTo(Carbon::parse('2026-06-01 01:00:00'));
        UsageEvent::factory()->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
        ]);

        $this->travelTo(Carbon::parse('2026-09-27'));

        $this->artisan('usage:rollup')->assertExitCode(0);

        $this->assertSame(2, UsageMonthly::count());
    }

    #[Test]
    public function null_dimensions_roll_into_one_row(): void
    {
        $this->travelTo(now()->subDays(100));
        UsageEvent::factory()->count(2)->create([
            'feed_hash' => str_repeat('a', 64),
            'feed_host' => 'example.org',
            'referrer_host' => null,
        ]);

        $this->travelBack();

        $this->artisan('usage:rollup')->assertExitCode(0);

        $this->assertSame(1, UsageMonthly::count());

        $monthly = UsageMonthly::first();
        $this->assertSame(2, $monthly->count);
        $this->assertSame('', $monthly->referrer_host);
    }

    #[Test]
    public function rollup_is_scheduled(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $matches = collect($schedule->events())->contains(
            fn ($event): bool => str_contains($event->command, 'usage:rollup')
        );

        $this->assertTrue($matches);
    }
}
