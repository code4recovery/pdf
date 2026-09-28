function percentChange(now, before) {
    if (before === 0) {
        return now === 0 ? 'No change on the 30 days before' : 'None in the 30 days before';
    }

    const change = Math.round(((now - before) / before) * 100);

    return `${change > 0 ? '▲' : change < 0 ? '▼' : ''} ${Math.abs(change)}% on the 30 days before (${before})`.trim();
}

function rate(part, whole) {
    return whole === 0 ? '—' : `${((part / whole) * 100).toFixed(1)}%`;
}

function Card({ title, value, detail, children }) {
    return (
        <div className="dash-card">
            <div className="dash-card-title">{title}</div>
            <div className="dash-card-value">{value}</div>
            <div className="dash-card-detail">{detail}</div>
            {children && <div className="dash-card-detail">{children}</div>}
        </div>
    );
}

/**
 * Three headline cards beside the sources chart: recent volume, adoption and recent reliability.
 * Rendered as siblings so they sit in the same grid as the sources card and share its height.
 */
export default function StatCards({ highlights, failures }) {
    const allTimeFailed = failures.rows.reduce((sum, row) => sum + row.count, 0);

    return (
        <>
            <Card
                title="PDFs, last 30 days"
                value={highlights.pdfs_30}
                detail={percentChange(highlights.pdfs_30, highlights.pdfs_prev_30)}
            >
                from {highlights.active_feeds_30} {highlights.active_feeds_30 === 1 ? 'feed' : 'feeds'}
            </Card>
            <Card title="Feeds, all time" value={highlights.total_feeds} detail={`${highlights.new_feeds_30} new in the last 30 days`} />
            <Card
                title="Failure rate, last 30 days"
                value={rate(highlights.failures_30, highlights.requests_30)}
                detail={`${highlights.failures_30} of ${highlights.requests_30} PDF requests`}
            >
                All time: {rate(allTimeFailed, failures.requests)}
            </Card>
        </>
    );
}
