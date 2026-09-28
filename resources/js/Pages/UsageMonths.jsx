import { Link } from '@inertiajs/react';
import MonthlyTable from '../Components/MonthlyTable';

export default function UsageMonths({ monthly }) {
    return (
        <main className="container-lg my-5">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1 className="h3 mb-0">Monthly Activity</h1>
                <Link href="/usage" className="btn btn-secondary btn-sm">
                    Back to dashboard
                </Link>
            </div>

            <p className="text-muted">Every month since the service started recording, newest first.</p>

            <MonthlyTable monthly={monthly} />
        </main>
    );
}
