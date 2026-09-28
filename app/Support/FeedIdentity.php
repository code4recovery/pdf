<?php

namespace App\Support;

/**
 * Identifies a feed for usage analytics. Google Sheets links act as access
 * credentials, so for them only the hash, host, and source type are kept;
 * public feeds also keep their address, minus any query parameter other
 * than `action` (query strings can carry access keys).
 */
final class FeedIdentity
{
    /** A host longer than this can never be a valid DNS name (max 253 chars). */
    private const MAX_HOST_LENGTH = 253;

    /** Matches the `feed_url` column length. */
    private const MAX_URL_LENGTH = 2048;

    public function __construct(
        public readonly ?string $hash,
        public readonly ?string $host,
        public readonly string $sourceType,
        public readonly ?string $url = null,
    ) {}

    public static function fromUrl(?string $url): self
    {
        $trimmed = trim((string) $url);

        if ($trimmed === '') {
            return new self(null, null, 'none');
        }

        $parts = parse_url($trimmed);
        $host = isset($parts['host']) ? strtolower($parts['host']) : null;

        if ($host === null) {
            return new self(hash('sha256', $trimmed), null, 'json');
        }

        $host = preg_replace('/^www\./', '', $host);
        $path = $parts['path'] ?? '';

        if ($host === 'docs.google.com' && preg_match('#^/spreadsheets/d/([^/]+)#', $path, $matches)) {
            return new self(hash('sha256', 'gsheet:' . $matches[1]), 'docs.google.com', 'google_sheet');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? null;
        $defaultPort = match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
        $portSegment = ($port !== null && $port !== $defaultPort) ? ':' . $port : '';

        $path = rtrim($path, '/');

        $queryParams = [];
        $sortedQuery = '';
        $query = $parts['query'] ?? '';

        if ($query !== '') {
            parse_str($query, $queryParams);
            ksort($queryParams);
            $sortedQuery = http_build_query($queryParams);
        }

        $normalized = $host . $portSegment . $path . ($sortedQuery !== '' ? '?' . $sortedQuery : '');

        return new self(
            hash('sha256', $normalized),
            strlen($host) > self::MAX_HOST_LENGTH ? null : $host,
            self::detectSourceType($path, $queryParams),
            self::publicUrl($parts, $scheme, $queryParams),
        );
    }

    /**
     * The address to store for a public feed: scheme, host, port and path as given,
     * plus `action` when present. Null for anything that isn't a plain http(s) URL.
     *
     * @param  array<string, mixed>  $parts
     * @param  array<string, mixed>  $queryParams
     */
    private static function publicUrl(array $parts, string $scheme, array $queryParams): ?string
    {
        if (!in_array($scheme, ['http', 'https'], true) || strlen($parts['host']) > self::MAX_HOST_LENGTH) {
            return null;
        }

        $url = $scheme . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');

        if (is_string($queryParams['action'] ?? null)) {
            $url .= '?' . http_build_query(['action' => $queryParams['action']]);
        }

        return strlen($url) > self::MAX_URL_LENGTH ? null : $url;
    }

    /**
     * @param  array<string, mixed>  $queryParams
     */
    private static function detectSourceType(string $path, array $queryParams): string
    {
        if (str_ends_with($path, '/wp-admin/admin-ajax.php') && ($queryParams['action'] ?? null) === 'meetings') {
            return 'tsml';
        }

        if (str_contains($path, '/wp-json/tsml')) {
            return 'tsml';
        }

        return 'json';
    }
}
