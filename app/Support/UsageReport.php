<?php

namespace App\Support;

use App\Http\Middleware\RecordUsage;
use App\Models\FeedLabel;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use Code4Recovery\Spec;
use Illuminate\Support\Carbon;

/**
 * Reads usage_events (the raw, ~90-day window) and usage_monthly (the
 * rolled-up history beyond that window) and aggregates them into the
 * shapes the /usage dashboard renders.
 *
 * Counting is done by the database with GROUP BY, so memory and time grow
 * with the number of feeds, referrers and months, never with the number of
 * individual requests. Settings are the one exception: they are streamed row
 * by row from the raw window (see settings()).
 *
 * "pdfs" always means `pdf_generated` events with outcome `success`,
 * consistently across every method here.
 */
final class UsageReport
{
    /**
     * Setting keys shown on the dashboard, in display order. `settings`
     * is only ever recorded on raw `usage_events` rows (usage_monthly
     * has no settings column), so this method never sees rolled-up data.
     *
     * @var list<string>
     */
    private const SETTING_KEYS = [
        'group_by',
        'language',
        'paper',
        'font',
        'mode',
        'front_cover',
        'back_cover',
        'regions_selected',
    ];

    /**
     * Newest month first, every month from the first with activity through the current one,
     * quiet months included as zeros. `$months` limits it to the most recent months; null means all time.
     *
     * @return list<array{month: string, pdfs: int, forms_opened: int, failures: int, unique_feeds: int}>
     */
    public function monthly(?int $months = 24): array
    {
        $cutoff = $months === null ? null : now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $buckets = [];

        $rolledUp = UsageMonthly::query()
            ->when($cutoff, fn ($query) => $query->where('month', '>=', $cutoff->toDateString()));
        $raw = UsageEvent::query()
            ->when($cutoff, fn ($query) => $query->where('created_at', '>=', $cutoff));

        $counts = [
            ...(clone $rolledUp)->toBase()
                ->selectRaw('SUBSTR(month, 1, 7) as ym, event, outcome, SUM(count) as total')
                ->groupByRaw('SUBSTR(month, 1, 7), event, outcome')
                ->get(),
            ...(clone $raw)->toBase()
                ->selectRaw($this->eventMonthSql() . ' as ym, event, outcome, COUNT(*) as total')
                ->groupByRaw($this->eventMonthSql() . ', event, outcome')
                ->get(),
        ];

        foreach ($counts as $row) {
            $bucket = &$this->bucket($buckets, $row->ym);
            $this->applyMonthlyCount($bucket, $row->event, $row->outcome, (int) $row->total);
            unset($bucket);
        }

        $feedMonths = [
            ...(clone $rolledUp)->toBase()
                ->where('event', 'pdf_generated')->where('feed_hash', '!=', '')
                ->selectRaw('DISTINCT SUBSTR(month, 1, 7) as ym, feed_hash')
                ->get(),
            ...(clone $raw)->toBase()
                ->where('event', 'pdf_generated')->whereNotNull('feed_hash')
                ->selectRaw('DISTINCT ' . $this->eventMonthSql() . ' as ym, feed_hash')
                ->get(),
        ];

        foreach ($feedMonths as $row) {
            $this->bucket($buckets, $row->ym)['feeds'][$row->feed_hash] = true;
        }

        $month = $this->firstActiveMonth();

        if ($month !== null) {
            if ($cutoff !== null && $month->lt($cutoff)) {
                $month = $cutoff->copy();
            }

            $thisMonth = now()->startOfMonth();

            while ($month->lte($thisMonth)) {
                $this->bucket($buckets, $month->format('Y-m'));
                $month->addMonthNoOverflow();
            }
        }

        krsort($buckets);

        return array_map(
            fn (string $month, array $bucket): array => [
                'month' => $month,
                'pdfs' => $bucket['pdfs'],
                'forms_opened' => $bucket['forms_opened'],
                'failures' => $bucket['failures'],
                'unique_feeds' => count($bucket['feeds']),
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * Headline numbers for the dashboard's cards: the last 30 days against the 30 before, how many feeds have
     * ever made a PDF and how many did so for the first time in the last 30 days, and the recent failure count.
     * Both 30-day windows fall inside the ~90-day raw table, so these read raw rows only, apart from total
     * feeds and "new", which also consult rolled-up months so an old feed returning isn't counted as new.
     *
     * @return array{pdfs_30: int, pdfs_prev_30: int, active_feeds_30: int, total_feeds: int, new_feeds_30: int, requests_30: int, failures_30: int}
     */
    public function highlights(): array
    {
        $since = now()->subDays(30);
        $previous = now()->subDays(60);

        $successes = fn () => UsageEvent::query()->toBase()->where('event', 'pdf_generated')->where('outcome', 'success');

        $recentFeeds = $successes()->where('created_at', '>=', $since)->whereNotNull('feed_hash')->distinct()->pluck('feed_hash')->all();
        $earlierFeeds = [
            ...$successes()->where('created_at', '<', $since)->whereNotNull('feed_hash')->distinct()->pluck('feed_hash')->all(),
            ...UsageMonthly::query()->toBase()->where('event', 'pdf_generated')->where('outcome', 'success')
                ->where('feed_hash', '!=', '')->distinct()->pluck('feed_hash')->all(),
        ];

        $requests = UsageEvent::query()->toBase()
            ->where('event', 'pdf_generated')
            ->where('created_at', '>=', $since)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN outcome = 'success' THEN 0 ELSE 1 END) as failed")
            ->first();

        return [
            'pdfs_30' => $successes()->where('created_at', '>=', $since)->count(),
            'pdfs_prev_30' => $successes()->where('created_at', '>=', $previous)->where('created_at', '<', $since)->count(),
            'active_feeds_30' => count($recentFeeds),
            'total_feeds' => count(array_unique([...$recentFeeds, ...$earlierFeeds])),
            'new_feeds_30' => count(array_diff($recentFeeds, $earlierFeeds)),
            'requests_30' => (int) $requests->total,
            'failures_30' => (int) $requests->failed,
        ];
    }

    /**
     * The last 12 months, oldest first, each keyed by 'Y-m' with a zero count.
     *
     * @return array<string, int>
     */
    private function trendMonths(): array
    {
        $months = [];
        $month = now()->startOfMonth()->subMonthsNoOverflow(11);

        for ($i = 0; $i < 12; $i++) {
            $months[$month->format('Y-m')] = 0;
            $month->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  array{trend: array<string, int>}  $bucket
     * @param  array<string, int>  $trendMonths
     */
    private function addToTrend(array &$bucket, array $trendMonths, string $month, int $count): void
    {
        if (array_key_exists($month, $trendMonths)) {
            $bucket['trend'][$month] = ($bucket['trend'][$month] ?? 0) + $count;
        }
    }

    /**
     * The first month with any recorded activity in either table, or null if there is none.
     */
    private function firstActiveMonth(): ?Carbon
    {
        $firstEvent = UsageEvent::query()->min('created_at');
        $candidates = array_filter([
            UsageMonthly::query()->min('month'),
            $firstEvent === null ? null : substr((string) $firstEvent, 0, 10),
        ]);

        return $candidates === [] ? null : Carbon::parse(min($candidates))->startOfMonth();
    }

    /**
     * The SQL expression for a usage_events row's month as 'YYYY-MM', for the connection in use.
     */
    private function eventMonthSql(): string
    {
        return UsageEvent::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
    }

    /**
     * @param  array{pdfs: int, forms_opened: int, failures: int, feeds: array<string, true>}  $bucket
     */
    private function applyMonthlyCount(array &$bucket, string $event, string $outcome, int $count): void
    {
        if ($event === 'pdf_generated') {
            if ($outcome === 'success') {
                $bucket['pdfs'] += $count;
            } else {
                $bucket['failures'] += $count;
            }
        } elseif ($event === 'form_opened') {
            $bucket['forms_opened'] += $count;
        }
    }

    /**
     * @param  array<string, array{pdfs: int, forms_opened: int, failures: int, feeds: array<string, true>}>  $buckets
     * @return array{pdfs: int, forms_opened: int, failures: int, feeds: array<string, true>}
     */
    private function &bucket(array &$buckets, string $key): array
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['pdfs' => 0, 'forms_opened' => 0, 'failures' => 0, 'feeds' => []];
        }

        return $buckets[$key];
    }

    /**
     * @return list<array{source_type: string, pdfs: int, feeds: int}>
     */
    public function sources(): array
    {
        $buckets = [];

        $rolledUp = UsageMonthly::query()->where('event', 'pdf_generated')->where('outcome', 'success');
        $raw = UsageEvent::query()->where('event', 'pdf_generated')->where('outcome', 'success');

        $counts = [
            ...(clone $rolledUp)->toBase()->selectRaw('source_type, SUM(count) as total')->groupBy('source_type')->get(),
            ...(clone $raw)->toBase()->selectRaw('source_type, COUNT(*) as total')->groupBy('source_type')->get(),
        ];

        foreach ($counts as $row) {
            $this->sourceBucket($buckets, $row->source_type)['pdfs'] += (int) $row->total;
        }

        $feeds = [
            ...(clone $rolledUp)->toBase()->where('feed_hash', '!=', '')->distinct()->get(['source_type', 'feed_hash']),
            ...(clone $raw)->toBase()->whereNotNull('feed_hash')->distinct()->get(['source_type', 'feed_hash']),
        ];

        foreach ($feeds as $row) {
            $this->sourceBucket($buckets, $row->source_type)['feeds'][$row->feed_hash] = true;
        }

        $rows = array_map(
            fn (string $sourceType, array $bucket): array => [
                'source_type' => $sourceType,
                'pdfs' => $bucket['pdfs'],
                'feeds' => count($bucket['feeds']),
            ],
            array_keys($buckets),
            $buckets,
        );

        usort($rows, fn (array $a, array $b): int => $b['pdfs'] <=> $a['pdfs']);

        return $rows;
    }

    /**
     * @param  array<string, array{pdfs: int, feeds: array<string, true>}>  $buckets
     * @return array{pdfs: int, feeds: array<string, true>}
     */
    private function &sourceBucket(array &$buckets, string $key): array
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['pdfs' => 0, 'feeds' => []];
        }

        return $buckets[$key];
    }

    /**
     * The busiest feeds of all time, most PDFs first; ties go to the feed used most recently.
     *
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string, trend: list<int>}>
     */
    public function topFeeds(int $limit = 25): array
    {
        $rows = $this->feedRows();

        usort($rows, fn (array $a, array $b): int => [$b['pdfs'], $b['last_used'], $a['fingerprint']] <=> [$a['pdfs'], $a['last_used'], $b['fingerprint']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Every feed that has produced a PDF, alphabetical by label, then host, then fingerprint.
     *
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string, trend: list<int>}>
     */
    public function feeds(): array
    {
        $rows = $this->feedRows();

        usort($rows, fn (array $a, array $b): int => strcasecmp(
            $a['label'] ?? $a['host'] ?? $a['fingerprint'],
            $b['label'] ?? $b['host'] ?? $b['fingerprint'],
        ) ?: strcmp($a['fingerprint'], $b['fingerprint']));

        return $rows;
    }

    /**
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string, trend: list<int>}>
     */
    private function feedRows(): array
    {
        $buckets = [];
        $trendMonths = $this->trendMonths();
        $trendStart = array_key_first($trendMonths);

        $rolledUp = UsageMonthly::query()->where('event', 'pdf_generated')->where('outcome', 'success')->where('feed_hash', '!=', '');
        $raw = UsageEvent::query()->where('event', 'pdf_generated')->where('outcome', 'success')->whereNotNull('feed_hash');

        $totals = (clone $rolledUp)->toBase()
            ->selectRaw("feed_hash, SUM(count) as total, MAX(month) as last_used, MAX(source_type) as source_type, MAX(NULLIF(feed_host, '')) as host, MAX(feed_url) as url")
            ->groupBy('feed_hash')
            ->get();

        foreach ($totals as $row) {
            $bucket = &$this->feedBucket($buckets, $row->feed_hash);
            $bucket['pdfs'] += (int) $row->total;
            $bucket['source_type'] = $row->source_type;
            $bucket['host'] = $row->host ?? $bucket['host'];
            $bucket['url'] = $row->url ?? $bucket['url'];
            $this->extendLastUsed($bucket, substr((string) $row->last_used, 0, 10));
            unset($bucket);
        }

        // Raw rows are newer than rolled-up months, so their host, address and source win.
        $totals = (clone $raw)->toBase()
            ->selectRaw('feed_hash, COUNT(*) as total, MAX(created_at) as last_used, MAX(source_type) as source_type, MAX(feed_host) as host, MAX(feed_url) as url')
            ->groupBy('feed_hash')
            ->get();

        foreach ($totals as $row) {
            $bucket = &$this->feedBucket($buckets, $row->feed_hash);
            $bucket['pdfs'] += (int) $row->total;
            $bucket['source_type'] = $row->source_type;
            $bucket['host'] = $row->host ?? $bucket['host'];
            $bucket['url'] = $row->url ?? $bucket['url'];
            $this->extendLastUsed($bucket, substr((string) $row->last_used, 0, 10));
            unset($bucket);
        }

        $trend = [
            ...(clone $rolledUp)->toBase()
                ->where('month', '>=', $trendStart . '-01')
                ->selectRaw('feed_hash, SUBSTR(month, 1, 7) as ym, SUM(count) as total')
                ->groupByRaw('feed_hash, SUBSTR(month, 1, 7)')
                ->get(),
            ...(clone $raw)->toBase()
                ->where('created_at', '>=', $trendStart . '-01')
                ->selectRaw('feed_hash, ' . $this->eventMonthSql() . ' as ym, COUNT(*) as total')
                ->groupByRaw('feed_hash, ' . $this->eventMonthSql())
                ->get(),
        ];

        foreach ($trend as $row) {
            $bucket = &$this->feedBucket($buckets, $row->feed_hash);
            $this->addToTrend($bucket, $trendMonths, $row->ym, (int) $row->total);
            unset($bucket);
        }

        $latest = (clone $raw)->toBase()
            ->whereNotNull('meeting_count')
            ->selectRaw('feed_hash, MAX(created_at) as latest')
            ->groupBy('feed_hash');

        $meetings = UsageEvent::query()->toBase()
            ->joinSub($latest, 'latest', fn ($join) => $join
                ->on('usage_events.feed_hash', '=', 'latest.feed_hash')
                ->on('usage_events.created_at', '=', 'latest.latest'))
            ->where('usage_events.event', 'pdf_generated')
            ->where('usage_events.outcome', 'success')
            ->whereNotNull('usage_events.meeting_count')
            ->orderBy('usage_events.id')
            ->get(['usage_events.feed_hash', 'usage_events.meeting_count']);

        foreach ($meetings as $row) {
            $buckets[$row->feed_hash]['meetings'] = (int) $row->meeting_count;
        }

        $labels = FeedLabel::query()->whereIn('feed_hash', array_keys($buckets))->pluck('label', 'feed_hash');

        return array_map(
            fn (string $hash, array $bucket): array => [
                'fingerprint' => substr($hash, 0, 12),
                'label' => $labels[$hash] ?? null,
                'host' => $bucket['host'],
                'url' => $bucket['url'],
                'source_type' => $bucket['source_type'],
                'pdfs' => $bucket['pdfs'],
                'meetings' => $bucket['meetings'],
                'last_used' => $bucket['last_used'],
                'trend' => array_values(array_replace($trendMonths, $bucket['trend'])),
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * @param  array<string, array{pdfs: int, source_type: string, host: ?string, url: ?string, last_used: string, meetings: ?int, trend: array<string, int>}>  $buckets
     * @return array{pdfs: int, source_type: string, host: ?string, url: ?string, last_used: string, meetings: ?int, trend: array<string, int>}
     */
    private function &feedBucket(array &$buckets, string $key): array
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['pdfs' => 0, 'source_type' => 'json', 'host' => null, 'last_used' => '0000-00-00', 'url' => null, 'meetings' => null, 'trend' => []];
        }

        return $buckets[$key];
    }

    /**
     * @param  array{last_used: string}  $bucket
     */
    private function extendLastUsed(array &$bucket, string $date): void
    {
        if ($date > $bucket['last_used']) {
            $bucket['last_used'] = $date;
        }
    }

    /**
     * The referring sites that sent the most traffic, all time.
     *
     * @return list<array{host: string, forms_opened: int, pdfs: int}>
     */
    public function topReferrers(int $limit = 15): array
    {
        $rows = $this->referrerRows();

        usort($rows, fn (array $a, array $b): int => [$b['forms_opened'], $b['pdfs']] <=> [$a['forms_opened'], $a['pdfs']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Every referring site, alphabetical by host, all time, each with the feeds it sent visitors to
     * (busiest first).
     *
     * @return list<array{host: string, forms_opened: int, pdfs: int, feeds: list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, forms_opened: int, pdfs: int}>}>
     */
    public function referrers(): array
    {
        $rows = $this->referrerRows();
        $feeds = $this->referrerFeeds();

        foreach ($rows as &$row) {
            $row['feeds'] = $feeds[$row['host']] ?? [];
        }

        unset($row);

        usort($rows, fn (array $a, array $b): int => strcasecmp($a['host'], $b['host']));

        return $rows;
    }

    /**
     * Feeds per referring site, grouped in the database by referrer and feed.
     *
     * @return array<string, list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, forms_opened: int, pdfs: int}>>
     */
    private function referrerFeeds(): array
    {
        $counts = [
            ...UsageMonthly::query()->toBase()
                ->where('referrer_host', '!=', '')->where('feed_hash', '!=', '')
                ->selectRaw("referrer_host, feed_hash, event, outcome, SUM(count) as total, MAX(source_type) as source_type, MAX(NULLIF(feed_host, '')) as host, MAX(feed_url) as url")
                ->groupBy('referrer_host', 'feed_hash', 'event', 'outcome')
                ->get(),
            ...UsageEvent::query()->toBase()
                ->whereNotNull('referrer_host')->whereNotNull('feed_hash')
                ->selectRaw('referrer_host, feed_hash, event, outcome, COUNT(*) as total, MAX(source_type) as source_type, MAX(feed_host) as host, MAX(feed_url) as url')
                ->groupBy('referrer_host', 'feed_hash', 'event', 'outcome')
                ->get(),
        ];

        $buckets = [];

        foreach ($counts as $row) {
            $bucket = &$buckets[$row->referrer_host][$row->feed_hash];
            $bucket ??= ['forms_opened' => 0, 'pdfs' => 0, 'source_type' => 'json', 'host' => null, 'url' => null];
            $this->applyReferrerCount($bucket, $row->event, $row->outcome, (int) $row->total);
            $bucket['source_type'] = $row->source_type;
            $bucket['host'] = $row->host ?? $bucket['host'];
            $bucket['url'] = $row->url ?? $bucket['url'];
            unset($bucket);
        }

        $hashes = [];

        foreach ($buckets as $feeds) {
            $hashes += array_fill_keys(array_keys($feeds), true);
        }

        $labels = FeedLabel::query()->whereIn('feed_hash', array_keys($hashes))->pluck('label', 'feed_hash');

        $result = [];

        foreach ($buckets as $referrer => $feeds) {
            $list = [];

            foreach ($feeds as $hash => $feed) {
                $list[] = [
                    'fingerprint' => substr($hash, 0, 12),
                    'label' => $labels[$hash] ?? null,
                    'host' => $feed['host'],
                    'url' => $feed['url'],
                    'source_type' => $feed['source_type'],
                    'forms_opened' => $feed['forms_opened'],
                    'pdfs' => $feed['pdfs'],
                ];
            }

            usort($list, fn (array $a, array $b): int => [$b['pdfs'], $b['forms_opened'], $a['fingerprint']] <=> [$a['pdfs'], $a['forms_opened'], $b['fingerprint']]);

            $result[$referrer] = $list;
        }

        return $result;
    }

    /**
     * @return list<array{host: string, forms_opened: int, pdfs: int}>
     */
    private function referrerRows(): array
    {
        $buckets = [];

        $counts = [
            ...UsageMonthly::query()->toBase()
                ->where('referrer_host', '!=', '')
                ->selectRaw('referrer_host, event, outcome, SUM(count) as total')
                ->groupBy('referrer_host', 'event', 'outcome')
                ->get(),
            ...UsageEvent::query()->toBase()
                ->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, event, outcome, COUNT(*) as total')
                ->groupBy('referrer_host', 'event', 'outcome')
                ->get(),
        ];

        foreach ($counts as $row) {
            $bucket = &$this->referrerBucket($buckets, $row->referrer_host);
            $this->applyReferrerCount($bucket, $row->event, $row->outcome, (int) $row->total);
            unset($bucket);
        }

        return array_map(
            fn (string $host, array $bucket): array => [
                'host' => $host,
                'forms_opened' => $bucket['forms_opened'],
                'pdfs' => $bucket['pdfs'],
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * @param  array<string, array{forms_opened: int, pdfs: int}>  $buckets
     * @return array{forms_opened: int, pdfs: int}
     */
    private function &referrerBucket(array &$buckets, string $key): array
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['forms_opened' => 0, 'pdfs' => 0];
        }

        return $buckets[$key];
    }

    /**
     * @param  array{forms_opened: int, pdfs: int, ...}  $bucket
     */
    private function applyReferrerCount(array &$bucket, string $event, string $outcome, int $count): void
    {
        if ($event === 'form_opened') {
            $bucket['forms_opened'] += $count;
        } elseif ($event === 'pdf_generated' && $outcome === 'success') {
            $bucket['pdfs'] += $count;
        }
    }

    /**
     * Every PDF request that did not succeed, by outcome and the feed's own HTTP status, most frequent first,
     * alongside the total number of PDF requests so the dashboard can show a failure rate. All time.
     *
     * @return array{requests: int, rows: list<array{outcome: string, upstream_status: ?int, count: int}>}
     */
    public function failures(): array
    {
        $requests = 0;
        $buckets = [];

        // usage_monthly keeps no feed status, so rolled-up failures group under a status of null.
        $counts = [
            ...UsageMonthly::query()->toBase()
                ->where('event', 'pdf_generated')
                ->selectRaw('outcome, NULL as upstream_status, SUM(count) as total')
                ->groupBy('outcome')
                ->get(),
            ...UsageEvent::query()->toBase()
                ->where('event', 'pdf_generated')
                ->selectRaw('outcome, upstream_status, COUNT(*) as total')
                ->groupBy('outcome', 'upstream_status')
                ->get(),
        ];

        foreach ($counts as $row) {
            $requests += (int) $row->total;

            if ($row->outcome !== 'success') {
                $status = $row->upstream_status === null ? null : (int) $row->upstream_status;
                $key = $row->outcome . '|' . ($status ?? '');
                $buckets[$key] ??= ['outcome' => $row->outcome, 'upstream_status' => $status, 'count' => 0];
                $buckets[$key]['count'] += (int) $row->total;
            }
        }

        $rows = array_values($buckets);

        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['requests' => $requests, 'rows' => $rows];
    }

    /**
     * @return list<array{setting: string, value: string, count: int}>
     */
    public function settings(): array
    {
        $tallies = array_fill_keys(self::SETTING_KEYS, []);

        // Settings live in a JSON column whose extraction syntax differs between SQLite and MySQL, so they are
        // streamed one row at a time (cursor) rather than grouped in SQL: memory stays flat however many rows there are.
        $rows = UsageEvent::query()->toBase()
            ->where('event', 'pdf_generated')
            ->whereNotNull('settings')
            ->select('settings')
            ->cursor();

        $seen = false;

        foreach ($rows as $row) {
            $seen = true;
            $settings = json_decode($row->settings, true);

            if (!is_array($settings)) {
                continue;
            }

            foreach (self::SETTING_KEYS as $key) {
                if (!array_key_exists($key, $settings)) {
                    continue;
                }

                $value = $this->displayValue($settings[$key]);
                $tallies[$key][$value] = ($tallies[$key][$value] ?? 0) + 1;
            }
        }

        if (!$seen) {
            return [];
        }

        $rows = [];

        foreach (self::SETTING_KEYS as $key) {
            // Every value the form offers is listed, unused ones as zero; recorded values come first, most common first.
            $values = $tallies[$key] + array_fill_keys($this->knownSettingValues($key), 0);
            arsort($values);

            foreach ($values as $value => $count) {
                $rows[] = ['setting' => $key, 'value' => $value, 'count' => $count];
            }
        }

        return $rows;
    }

    /**
     * The values the form offers for a setting, as they are stored. Paper size is free-form (any width and
     * height), so it has no fixed list and only shows sizes that were actually used.
     *
     * @return list<string>
     */
    private function knownSettingValues(string $key): array
    {
        return match ($key) {
            'group_by' => RecordUsage::GROUP_BY_VALUES,
            'language' => array_map('strval', array_keys(Spec::getLanguages())),
            'font' => RecordUsage::FONT_VALUES,
            'mode' => RecordUsage::MODE_VALUES,
            'front_cover', 'back_cover', 'regions_selected' => ['false', 'true'],
            default => [],
        };
    }

    /**
     * Never throws for non-scalar values, so a pre-existing row written
     * before settings were normalised (e.g. an array under a key that is
     * now enum-only) can't 500 the dashboard.
     */
    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: 'other';
    }

    /**
     * @return list<array{when: string, label_or_host: ?string, meeting_count: ?int, chunked: ?bool, duration_ms: int, peak_memory_mb: int}>
     */
    public function heaviest(int $limit = 10): array
    {
        $events = UsageEvent::query()
            ->where('event', 'pdf_generated')
            ->orderByDesc('peak_memory_mb')
            ->limit($limit)
            ->get();

        $labels = FeedLabel::query()
            ->whereIn('feed_hash', $events->pluck('feed_hash')->filter()->all())
            ->pluck('label', 'feed_hash');

        return $events->map(fn (UsageEvent $event): array => [
            'when' => $event->created_at->toDateTimeString(),
            'label_or_host' => ($event->feed_hash ? $labels[$event->feed_hash] ?? null : null) ?? $event->feed_host,
            'meeting_count' => $event->meeting_count,
            'chunked' => $event->chunked,
            'duration_ms' => $event->duration_ms,
            'peak_memory_mb' => $event->peak_memory_mb,
        ])->all();
    }
}
