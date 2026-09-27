<?php

namespace App\Support;

/**
 * Fingerprints a feed URL for usage analytics without ever persisting the
 * URL itself — Google Sheets links act as access credentials, so only the
 * hash, host, and source type are safe to store.
 */
final class FeedIdentity
{
    /** A host longer than this can never be a valid DNS name (max 253 chars). */
    private const MAX_HOST_LENGTH = 253;

    public function __construct(
        public readonly ?string $hash,
        public readonly ?string $host,
        public readonly string $sourceType,
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
        );
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
