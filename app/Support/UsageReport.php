<?php

namespace App\Support;

use App\Models\FeedLabel;
use App\Models\UsageEvent;
use App\Models\UsageMonthly;
use Illuminate\Support\Carbon;

/**
 * Reads usage_events (the raw, ~90-day window) and usage_monthly (the
 * rolled-up history beyond that window) and aggregates them into the
 * shapes the /usage dashboard renders.
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
     * quiet months included as zeros.
     *
     * @return list<array{month: string, pdfs: int, forms_opened: int, failures: int, unique_feeds: int}>
     */
    public function monthly(int $months = 24): array
    {
        $cutoff = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $buckets = [];

        foreach (UsageMonthly::query()->where('month', '>=', $cutoff->toDateString())->get() as $row) {
            $bucket = &$this->bucket($buckets, substr($row->month, 0, 7));
            $this->applyMonthlyCount($bucket, $row->event, $row->outcome, $row->feed_hash, $row->count);
            unset($bucket);
        }

        foreach (UsageEvent::query()->where('created_at', '>=', $cutoff)->get() as $event) {
            $bucket = &$this->bucket($buckets, $event->created_at->format('Y-m'));
            $this->applyMonthlyCount($bucket, $event->event, $event->outcome, $event->feed_hash, 1);
            unset($bucket);
        }

        if ($buckets !== []) {
            $month = Carbon::createFromFormat('Y-m', min(array_keys($buckets)))->startOfMonth();
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
     * @param  array{pdfs: int, forms_opened: int, failures: int, feeds: array<string, true>}  $bucket
     */
    private function applyMonthlyCount(array &$bucket, string $event, string $outcome, ?string $feedHash, int $count): void
    {
        if ($event === 'pdf_generated') {
            if ($outcome === 'success') {
                $bucket['pdfs'] += $count;
            } else {
                $bucket['failures'] += $count;
            }

            if (!empty($feedHash)) {
                $bucket['feeds'][$feedHash] = true;
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

        foreach (UsageMonthly::query()->where('event', 'pdf_generated')->where('outcome', 'success')->get() as $row) {
            $bucket = &$this->sourceBucket($buckets, $row->source_type);
            $bucket['pdfs'] += $row->count;

            if ($row->feed_hash !== '') {
                $bucket['feeds'][$row->feed_hash] = true;
            }

            unset($bucket);
        }

        foreach (UsageEvent::query()->where('event', 'pdf_generated')->where('outcome', 'success')->get() as $event) {
            $bucket = &$this->sourceBucket($buckets, $event->source_type);
            $bucket['pdfs']++;

            if (!empty($event->feed_hash)) {
                $bucket['feeds'][$event->feed_hash] = true;
            }

            unset($bucket);
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
     * The busiest feeds of all time, most PDFs first.
     *
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string}>
     */
    public function topFeeds(int $limit = 25): array
    {
        $rows = $this->feedRows();

        usort($rows, fn (array $a, array $b): int => $b['pdfs'] <=> $a['pdfs']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Every feed that has produced a PDF, alphabetical by label, then host, then fingerprint.
     *
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string}>
     */
    public function feeds(): array
    {
        $rows = $this->feedRows();

        usort($rows, fn (array $a, array $b): int => strcasecmp(
            $a['label'] ?? $a['host'] ?? $a['fingerprint'],
            $b['label'] ?? $b['host'] ?? $b['fingerprint'],
        ));

        return $rows;
    }

    /**
     * @return list<array{fingerprint: string, label: ?string, host: ?string, url: ?string, source_type: string, pdfs: int, meetings: ?int, last_used: string}>
     */
    private function feedRows(): array
    {
        $buckets = [];

        foreach (UsageMonthly::query()->where('event', 'pdf_generated')->where('outcome', 'success')->where('feed_hash', '!=', '')->get() as $row) {
            $bucket = &$this->feedBucket($buckets, $row->feed_hash);
            $bucket['pdfs'] += $row->count;
            $bucket['source_type'] = $row->source_type;

            if ($row->feed_host !== '') {
                $bucket['host'] = $row->feed_host;
            }

            $bucket['url'] = $row->feed_url ?? $bucket['url'];

            $this->extendLastUsed($bucket, $row->month);
            unset($bucket);
        }

        foreach (UsageEvent::query()->where('event', 'pdf_generated')->where('outcome', 'success')->whereNotNull('feed_hash')->get() as $event) {
            $bucket = &$this->feedBucket($buckets, $event->feed_hash);
            $bucket['pdfs']++;
            $bucket['source_type'] = $event->source_type;

            if (!empty($event->feed_host)) {
                $bucket['host'] = $event->feed_host;
            }

            $bucket['url'] = $event->feed_url ?? $bucket['url'];

            $this->extendLastUsed($bucket, $event->created_at->toDateString());

            if ($event->meeting_count !== null && ($bucket['meetings_at'] === null || $event->created_at->gte($bucket['meetings_at']))) {
                $bucket['meetings'] = $event->meeting_count;
                $bucket['meetings_at'] = $event->created_at;
            }

            unset($bucket);
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
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * @param  array<string, array{pdfs: int, source_type: string, host: ?string, url: ?string, last_used: string, meetings: ?int, meetings_at: ?\Illuminate\Support\Carbon}>  $buckets
     * @return array{pdfs: int, source_type: string, host: ?string, url: ?string, last_used: string, meetings: ?int, meetings_at: ?\Illuminate\Support\Carbon}
     */
    private function &feedBucket(array &$buckets, string $key): array
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['pdfs' => 0, 'source_type' => 'json', 'host' => null, 'last_used' => '0000-00-00', 'url' => null, 'meetings' => null, 'meetings_at' => null];
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
     * @return list<array{host: string, forms_opened: int, pdfs: int}>
     */
    public function topReferrers(int $limit = 15): array
    {
        $buckets = [];

        foreach (UsageMonthly::query()->where('referrer_host', '!=', '')->get() as $row) {
            $bucket = &$this->referrerBucket($buckets, $row->referrer_host);
            $this->applyReferrerCount($bucket, $row->event, $row->outcome, $row->count);
            unset($bucket);
        }

        foreach (UsageEvent::query()->whereNotNull('referrer_host')->get() as $event) {
            $bucket = &$this->referrerBucket($buckets, $event->referrer_host);
            $this->applyReferrerCount($bucket, $event->event, $event->outcome, 1);
            unset($bucket);
        }

        $rows = array_map(
            fn (string $host, array $bucket): array => [
                'host' => $host,
                'forms_opened' => $bucket['forms_opened'],
                'pdfs' => $bucket['pdfs'],
            ],
            array_keys($buckets),
            $buckets,
        );

        usort($rows, fn (array $a, array $b): int => [$b['forms_opened'], $b['pdfs']] <=> [$a['forms_opened'], $a['pdfs']]);

        return array_slice($rows, 0, $limit);
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
     * @param  array{forms_opened: int, pdfs: int}  $bucket
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
     * @return list<array{outcome: string, upstream_status: ?int, count: int}>
     */
    public function outcomes(): array
    {
        $buckets = [];

        foreach (UsageMonthly::query()->where('event', 'pdf_generated')->get() as $row) {
            $key = $row->outcome . '|';
            $buckets[$key] ??= ['outcome' => $row->outcome, 'upstream_status' => null, 'count' => 0];
            $buckets[$key]['count'] += $row->count;
        }

        foreach (UsageEvent::query()->where('event', 'pdf_generated')->get() as $event) {
            $key = $event->outcome . '|' . ($event->upstream_status ?? '');
            $buckets[$key] ??= ['outcome' => $event->outcome, 'upstream_status' => $event->upstream_status, 'count' => 0];
            $buckets[$key]['count']++;
        }

        $rows = array_values($buckets);

        usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * @return list<array{setting: string, value: string, count: int}>
     */
    public function settings(): array
    {
        $tallies = array_fill_keys(self::SETTING_KEYS, []);

        foreach (UsageEvent::query()->where('event', 'pdf_generated')->whereNotNull('settings')->get() as $event) {
            foreach (self::SETTING_KEYS as $key) {
                if (!array_key_exists($key, $event->settings)) {
                    continue;
                }

                $value = $this->displayValue($event->settings[$key]);
                $tallies[$key][$value] = ($tallies[$key][$value] ?? 0) + 1;
            }
        }

        $rows = [];

        foreach (self::SETTING_KEYS as $key) {
            $values = $tallies[$key];
            arsort($values);

            foreach ($values as $value => $count) {
                $rows[] = ['setting' => $key, 'value' => $value, 'count' => $count];
            }
        }

        return $rows;
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
