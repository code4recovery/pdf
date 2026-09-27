<?php

namespace App\Http\Middleware;

use App\Models\UsageEvent;
use App\Support\FeedIdentity;
use App\Support\UsageRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes one usage_events row per home()/pdf() request, after the response
 * has already been sent. Recording must never affect the response, so the
 * write is wrapped in a try/catch that only reports failures.
 */
class RecordUsage
{
    public function __construct(private readonly UsageRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'home' && empty($request->input('json'))) {
            return;
        }

        try {
            $this->record($request, $response, $routeName);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function record(Request $request, Response $response, ?string $routeName): void
    {
        $status = $response->getStatusCode();
        $event = $routeName === 'home' ? 'form_opened' : 'pdf_generated';
        $feed = FeedIdentity::fromUrl($request->input('json'));

        UsageEvent::create([
            'event' => $event,
            'outcome' => $this->recorder->getOutcome() ?? ($status >= 500 ? 'error' : 'success'),
            'http_status' => $status,
            'source_type' => $feed->sourceType,
            'feed_hash' => $feed->hash,
            'feed_host' => $feed->host,
            'referrer_host' => $this->referrerHost($request),
            'meeting_count' => $this->recorder->getMeetingCount(),
            'region_count' => $this->recorder->getRegionCount(),
            'upstream_status' => $this->recorder->getUpstreamStatus(),
            'chunked' => $this->recorder->getChunked(),
            'duration_ms' => (int) round((microtime(true) - $this->requestStart()) * 1000),
            'peak_memory_mb' => (int) round(memory_get_peak_usage(true) / 1024 / 1024),
            'settings' => $event === 'pdf_generated' ? $this->settings($request) : null,
        ]);
    }

    /**
     * The timestamp the request started, in microseconds. `LARAVEL_START` is
     * defined by `public/index.php`, which the test runner never loads, so
     * `$_SERVER['REQUEST_TIME_FLOAT']` (set by PHP itself) is the fallback.
     */
    private function requestStart(): float
    {
        return defined('LARAVEL_START') ? \LARAVEL_START : (float) $_SERVER['REQUEST_TIME_FLOAT'];
    }

    /**
     * Lowercase host of the Referer header, `www.` stripped, or null when
     * absent, unparseable, or equal to the current request's own host.
     */
    private function referrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');

        if (empty($referer)) {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower($host));

        return $host === strtolower($request->getHost()) ? null : $host;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(Request $request): array
    {
        $width = $request->input('width', 4.25);
        $height = $request->input('height', 11);
        $regions = (array) $request->input('regions', []);
        $options = (array) $request->input('options', []);

        return [
            'group_by' => $request->input('group_by', 'day-region'),
            'language' => $request->input('language', 'en'),
            'paper' => $width . 'x' . $height,
            'font' => $request->input('font', 'serif'),
            'font_size' => (int) $request->input('font_size', 12),
            'mode' => $request->input('mode', 'download'),
            'numbering' => (bool) $request->input('numbering', false),
            'regions_selected' => !empty($regions),
            'front_cover' => $request->hasFile('front'),
            'back_cover' => $request->hasFile('back'),
            'options' => array_values($options),
        ];
    }
}
