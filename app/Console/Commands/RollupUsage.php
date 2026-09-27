<?php

namespace App\Console\Commands;

use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RollupUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:rollup {--days=90 : Roll up usage_events older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fold usage_events older than the retention window into usage_monthly and delete them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $eventsRolled = 0;
        $touchedMonthlyIds = [];

        DB::transaction(function () use ($cutoff, &$eventsRolled, &$touchedMonthlyIds): void {
            UsageEvent::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(1000, function (Collection $events) use (&$eventsRolled, &$touchedMonthlyIds): void {
                    $ids = $events->pluck('id')->all();
                    $eventsRolled += count($ids);

                    $groups = $events->groupBy(fn (UsageEvent $event): string => implode('|', [
                        $event->created_at->copy()->startOfMonth()->toDateString(),
                        $event->event,
                        $event->outcome,
                        $event->source_type,
                        $event->feed_hash ?? '',
                        $event->feed_host ?? '',
                        $event->referrer_host ?? '',
                    ]));

                    foreach ($groups as $group) {
                        /** @var UsageEvent $first */
                        $first = $group->first();

                        $monthly = UsageMonthly::firstOrCreate([
                            'month' => $first->created_at->copy()->startOfMonth()->toDateString(),
                            'event' => $first->event,
                            'outcome' => $first->outcome,
                            'source_type' => $first->source_type,
                            'feed_hash' => $first->feed_hash ?? '',
                            'feed_host' => $first->feed_host ?? '',
                            'referrer_host' => $first->referrer_host ?? '',
                        ], ['count' => 0]);

                        $monthly->increment('count', $group->count());

                        $touchedMonthlyIds[$monthly->id] = true;
                    }

                    UsageEvent::query()->whereIn('id', $ids)->delete();
                });
        });

        $this->info(sprintf('Rolled up %d events into %d monthly rows.', $eventsRolled, count($touchedMonthlyIds)));

        return self::SUCCESS;
    }
}
