import { Link } from '@inertiajs/react';
import DataTable from '../Components/DataTable';
import FeedName from '../Components/FeedName';
import { SourcePill } from '../Components/Pill';

const columns = [
    { id: 'host', header: 'Referring Site', accessorKey: 'host', sortFn: 'text' },
    { id: 'feeds', header: 'Feeds', accessorFn: (row) => row.feeds.length, sortDescFirst: true, meta: { numeric: true } },
    { id: 'forms_opened', header: 'Forms Opened', accessorKey: 'forms_opened', sortDescFirst: true, meta: { numeric: true } },
    { id: 'pdfs', header: 'PDFs', accessorKey: 'pdfs', sortDescFirst: true, meta: { numeric: true } },
];

/** The feeds a referring site sent visitors to, shown when its row is expanded. */
function ReferrerFeeds({ referrer }) {
    if (referrer.feeds.length === 0) {
        return <span className="text-muted">No feed recorded for these visits.</span>;
    }

    return (
        <table className="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Feed</th>
                    <th>Source</th>
                    <th className="num">Forms Opened</th>
                    <th className="num">PDFs</th>
                </tr>
            </thead>
            <tbody>
                {referrer.feeds.map((feed) => (
                    <tr key={feed.fingerprint}>
                        <td>
                            <FeedName feed={feed} />
                        </td>
                        <td>
                            <SourcePill sourceType={feed.source_type} />
                        </td>
                        <td className="num">{feed.forms_opened}</td>
                        <td className="num">{feed.pdfs}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function UsageReferrers({ referrers }) {
    return (
        <main className="container-lg my-5">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1 className="h3 mb-0">All Referrers</h1>
                <Link href="/usage" className="btn btn-secondary btn-sm">
                    Back to dashboard
                </Link>
            </div>

            <p className="text-muted">
                {referrers.length} {referrers.length === 1 ? 'site has' : 'sites have'} sent visitors here. Counts
                are all time and only include visits where the browser reported where it came from. Click a site to
                see which feeds it sent people to, or a column heading to sort.
            </p>

            <DataTable
                columns={columns}
                data={referrers}
                searchPlaceholder="Search sites"
                renderExpanded={(referrer) => <ReferrerFeeds referrer={referrer} />}
            />
        </main>
    );
}
