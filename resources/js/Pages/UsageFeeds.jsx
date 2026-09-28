import { Link } from '@inertiajs/react';
import DataTable from '../Components/DataTable';
import { sourceTypeLabel } from '../sourceTypes';

const columns = [
    {
        id: 'feed',
        header: 'Feed',
        accessorFn: (row) => row.label || row.host || '—',
        sortFn: 'text',
    },
    {
        id: 'fingerprint',
        header: 'Fingerprint',
        accessorKey: 'fingerprint',
        cell: ({ getValue }) => <code>{getValue()}</code>,
    },
    {
        id: 'source',
        header: 'Source',
        accessorFn: (row) => sourceTypeLabel(row.source_type),
        sortFn: 'text',
    },
    { id: 'pdfs', header: 'PDFs', accessorKey: 'pdfs', sortDescFirst: true },
    {
        id: 'meetings',
        header: 'Meetings',
        accessorFn: (row) => row.meetings ?? undefined,
        sortDescFirst: true,
        sortUndefined: 'last',
        cell: ({ getValue }) => getValue() ?? '—',
    },
    { id: 'last_used', header: 'Last Used', accessorKey: 'last_used', sortDescFirst: true },
];

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
                {feeds.length} {feeds.length === 1 ? 'feed has' : 'feeds have'} produced a PDF. Counts are all
                time. Click a column heading to sort.
            </p>

            <DataTable columns={columns} data={feeds} searchPlaceholder="Search feeds" />
        </main>
    );
}
