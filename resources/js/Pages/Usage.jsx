import { Link, router } from '@inertiajs/react';
import MonthlyChart from '../Components/MonthlyChart';
import { sourceTypeLabel } from '../sourceTypes';

function handleSignOut(e) {
    e.preventDefault();
    router.post('/logout');
}

function groupBySetting(settings) {
    const groups = [];
    const index = {};

    settings.forEach((row) => {
        if (!(row.setting in index)) {
            index[row.setting] = groups.length;
            groups.push({ setting: row.setting, values: [] });
        }

        groups[index[row.setting]].values.push(row);
    });

    return groups;
}

export default function Usage({
    monthly,
    sources,
    topFeeds,
    topReferrers,
    outcomes,
    settings,
    heaviest,
}) {
    const settingGroups = groupBySetting(settings);

    return (
        <main className="container-lg my-5">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1 className="h3 mb-0">Usage Dashboard</h1>
                <form onSubmit={handleSignOut}>
                    <button type="submit" className="btn btn-outline-secondary btn-sm">
                        Sign out
                    </button>
                </form>
            </div>

            <section className="mb-5">
                <h2 className="h5">Monthly Activity</h2>
                {monthly.length > 0 && <MonthlyChart monthly={monthly} />}
                <div className="table-responsive">
                    <table className="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>PDFs</th>
                                <th>Forms Opened</th>
                                <th>Failures</th>
                                <th>Unique Feeds</th>
                            </tr>
                        </thead>
                        <tbody>
                            {monthly.map((row) => (
                                <tr key={row.month}>
                                    <td>{row.month}</td>
                                    <td>{row.pdfs}</td>
                                    <td>{row.forms_opened}</td>
                                    <td>{row.failures}</td>
                                    <td>{row.unique_feeds}</td>
                                </tr>
                            ))}
                            {monthly.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Sources</h2>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>PDFs</th>
                                <th>Feeds</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sources.map((row) => (
                                <tr key={row.source_type}>
                                    <td>{sourceTypeLabel(row.source_type)}</td>
                                    <td>{row.pdfs}</td>
                                    <td>{row.feeds}</td>
                                </tr>
                            ))}
                            {sources.length === 0 && (
                                <tr>
                                    <td colSpan="3" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <div className="d-flex justify-content-between align-items-baseline">
                    <h2 className="h5">Top Feeds (all time)</h2>
                    <Link href="/usage/feeds" className="small">
                        View all feeds
                    </Link>
                </div>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Feed</th>
                                <th>Source</th>
                                <th>PDFs</th>
                                <th>Meetings</th>
                                <th>Last Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topFeeds.map((row) => (
                                <tr key={row.fingerprint}>
                                    <td>{row.label || row.host || row.fingerprint}</td>
                                    <td>{sourceTypeLabel(row.source_type)}</td>
                                    <td>{row.pdfs}</td>
                                    <td>{row.meetings ?? '—'}</td>
                                    <td>{row.last_used}</td>
                                </tr>
                            ))}
                            {topFeeds.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Top Referrers</h2>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Host</th>
                                <th>Forms Opened</th>
                                <th>PDFs</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topReferrers.map((row) => (
                                <tr key={row.host}>
                                    <td>{row.host}</td>
                                    <td>{row.forms_opened}</td>
                                    <td>{row.pdfs}</td>
                                </tr>
                            ))}
                            {topReferrers.length === 0 && (
                                <tr>
                                    <td colSpan="3" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Outcomes</h2>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Outcome</th>
                                <th>Upstream Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {outcomes.map((row) => (
                                <tr key={`${row.outcome}-${row.upstream_status}`}>
                                    <td>{row.outcome}</td>
                                    <td>{row.upstream_status ?? '—'}</td>
                                    <td>{row.count}</td>
                                </tr>
                            ))}
                            {outcomes.length === 0 && (
                                <tr>
                                    <td colSpan="3" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Settings</h2>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {settingGroups.map((group) =>
                                group.values.map((row, i) => (
                                    <tr key={`${group.setting}-${row.value}`}>
                                        {i === 0 && (
                                            <td rowSpan={group.values.length}>{group.setting}</td>
                                        )}
                                        <td>{row.value}</td>
                                        <td>{row.count}</td>
                                    </tr>
                                ))
                            )}
                            {settingGroups.length === 0 && (
                                <tr>
                                    <td colSpan="3" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Heaviest Requests</h2>
                <div className="table-responsive">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Feed</th>
                                <th>Meetings</th>
                                <th>Chunked</th>
                                <th>Duration (ms)</th>
                                <th>Peak Memory (MB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {heaviest.map((row, i) => (
                                <tr key={i}>
                                    <td>{row.when}</td>
                                    <td>{row.label_or_host || '—'}</td>
                                    <td>{row.meeting_count ?? '—'}</td>
                                    <td>{row.chunked ? 'Yes' : 'No'}</td>
                                    <td>{row.duration_ms}</td>
                                    <td>{row.peak_memory_mb}</td>
                                </tr>
                            ))}
                            {heaviest.length === 0 && (
                                <tr>
                                    <td colSpan="6" className="text-muted">
                                        No activity yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    );
}
