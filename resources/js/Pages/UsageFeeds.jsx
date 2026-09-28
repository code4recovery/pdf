import { Link } from '@inertiajs/react';
import { sourceTypeLabel } from '../sourceTypes';

export default function UsageFeeds({ feeds }) {
    return (
        <main className="container-lg my-5">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1 className="h3 mb-0">All Feeds</h1>
                <Link href="/usage" className="btn btn-outline-secondary btn-sm">
                    Back to dashboard
                </Link>
            </div>

            <p className="text-muted">
                {feeds.length} {feeds.length === 1 ? 'feed has' : 'feeds have'} produced a PDF, in
                alphabetical order. Counts are all time.
            </p>

            <div className="table-responsive">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Feed</th>
                            <th>Fingerprint</th>
                            <th>Source</th>
                            <th>PDFs</th>
                            <th>Last Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        {feeds.map((row) => (
                            <tr key={row.fingerprint}>
                                <td>{row.label || row.host || '—'}</td>
                                <td>
                                    <code>{row.fingerprint}</code>
                                </td>
                                <td>{sourceTypeLabel(row.source_type)}</td>
                                <td>{row.pdfs}</td>
                                <td>{row.last_used}</td>
                            </tr>
                        ))}
                        {feeds.length === 0 && (
                            <tr>
                                <td colSpan="5" className="text-muted">
                                    No activity yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </main>
    );
}
