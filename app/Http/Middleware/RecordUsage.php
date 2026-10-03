<?php

namespace App\Http\Middleware;

use App\Models\UsageEvent;
use App\Support\FeedIdentity;
use App\Support\UsageRecorder;
use Closure;
use Code4Recovery\Spec;
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
    /** @var list<string> */
    public const GROUP_BY_VALUES = ['day-region', 'day', 'region-day'];

    /** @var list<string> */
    public const FONT_VALUES = ['sans-serif', 'serif'];

    /** @var list<string> */
    public const MODE_VALUES = ['download', 'stream'];

    /** @var list<int> */
    private const FONT_SIZES = [8, 9, 10, 11, 12, 16, 20, 24];

    /** @var list<string> */
    private const OPTION_KEYS = ['legend', 'pagebreaks', 'long_address', 'time_24hr'];

    /** A host longer than this can never be a valid DNS name (max 253 chars). */
    private const MAX_HOST_LENGTH = 253;

    public function __construct(private readonly UsageRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->recorder->reset();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $routeName = $request->route()?->getName();

        if (! FeedIdentity::isUsable($request->input('json'))) {
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
            'feed_url' => $feed->url,
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

        if (strlen($host) > self::MAX_HOST_LENGTH) {
            return null;
        }

        return $host === strtolower($request->getHost()) ? null : $host;
    }

    /**
     * Normalises every setting to a known, storable shape so an arbitrary
     * request value (an array where a scalar is expected, an unrecognised
     * option, etc.) can never make it into the `settings` JSON column and
     * break the dashboard later.
     *
     * @return array<string, mixed>
     */
    private function settings(Request $request): array
    {
        $regions = (array) $request->input('regions', []);

        return [
            'group_by' => $this->enumSetting($request, 'group_by', self::GROUP_BY_VALUES, 'day-region'),
            'language' => $this->enumSetting($request, 'language', array_keys(Spec::getLanguages()), 'en'),
            'paper' => $this->paperSetting($request),
            'font' => $this->enumSetting($request, 'font', self::FONT_VALUES, 'serif'),
            'font_size' => $this->fontSizeSetting($request),
            'mode' => $this->enumSetting($request, 'mode', self::MODE_VALUES, 'download'),
            'numbering' => (bool) $request->input('numbering', false),
            'regions_selected' => !empty($regions),
            'front_cover' => $request->hasFile('front'),
            'back_cover' => $request->hasFile('back'),
            'options' => $this->optionsSetting($request),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function enumSetting(Request $request, string $key, array $allowed, string $default): string
    {
        $value = $request->input($key, $default);

        if (!is_scalar($value)) {
            return 'other';
        }

        return in_array((string) $value, $allowed, true) ? (string) $value : 'other';
    }

    private function paperSetting(Request $request): string
    {
        $width = $this->formatDimension($request->input('width', 4.25));
        $height = $this->formatDimension($request->input('height', 11));

        if ($width === null || $height === null) {
            return 'other';
        }

        return $width . 'x' . $height;
    }

    /**
     * Formats a numeric width/height to a string with no trailing zeros,
     * or null when the value isn't a plain numeric scalar.
     */
    private function formatDimension(mixed $value): ?string
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }

        return rtrim(rtrim(sprintf('%.10f', (float) $value), '0'), '.');
    }

    private function fontSizeSetting(Request $request): ?int
    {
        $value = $request->input('font_size', 12);

        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return in_array($intValue, self::FONT_SIZES, true) ? $intValue : null;
    }

    /**
     * @return list<string>
     */
    private function optionsSetting(Request $request): array
    {
        $options = $request->input('options', []);

        if (!is_array($options)) {
            return [];
        }

        $strings = array_filter($options, 'is_string');

        return array_values(array_unique(array_intersect($strings, self::OPTION_KEYS)));
    }
}
