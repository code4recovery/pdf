function isWebAddress(url) {
    try {
        return ['http:', 'https:'].includes(new URL(url).protocol);
    } catch {
        return false;
    }
}

/**
 * A feed's display name, linked to the feed itself (in a new tab) when its address is known.
 * Google Sheets feeds never have an address, so they render as plain text.
 */
export default function FeedName({ feed }) {
    const name = feed.label || feed.host || '—';

    if (!feed.url || !isWebAddress(feed.url)) {
        return name;
    }

    return (
        <a href={feed.url} target="_blank" rel="noopener noreferrer" title={feed.url}>
            {name}
        </a>
    );
}
