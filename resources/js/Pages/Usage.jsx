import { Link, router } from '@inertiajs/react';
import FeedName from '../Components/FeedName';
import MonthlyTable from '../Components/MonthlyTable';
import SourcesChart from '../Components/SourcesChart';
import Sparkline from '../Components/Sparkline';
import ThemeToggle from '../Components/ThemeToggle';
import { OutcomePill, Pill, SourcePill } from '../Components/Pill';
import '../../css/dashboard.css';

const OUTCOME_MEANINGS = {
    fetch_failed: "The feed couldn't be downloaded",
    parse_failed: "The feed downloaded but couldn't be read",
    cover_rejected: 'An uploaded cover was refused',
    error: 'Unexpected crash — check Sentry',
};

const FEED_STATUS_MEANINGS = {
    401: 'TSML data sharing is turned off',
    403: 'The feed site refused the request',
    404: "The feed address doesn't exist",
    500: 'The feed site had a server error',
};

function failureMeaning(row) {
    return FEED_STATUS_MEANINGS[row.upstream_status] ?? OUTCOME_MEANINGS[row.outcome] ?? '';
}

function percent(part, whole) {
    return whole === 0 ? '—' : `${((part / whole) * 100).toFixed(1)}%`;
}

function failureSummary({ requests, rows }) {
    if (requests === 0) {
        return 'No PDF requests yet.';
    }

    const failed = rows.reduce((sum, row) => sum + row.count, 0);

    return `${failed} of ${requests} PDF requests failed (${percent(failed, requests)}).`;
}

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
    failures,
    settings,
    heaviest,
}) {
    const settingGroups = groupBySetting(settings);

    return (
        <main className="container-lg my-5">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h1 className="h3 mb-0">PDF Usage Dashboard</h1>
                <div className="d-flex gap-2">
                    <ThemeToggle />
                    <form onSubmit={handleSignOut}>
                        <button type="submit" className="btn btn-secondary btn-sm">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>

            <section className="mb-5">
                <h2 className="h5">Sources (all time)</h2>
                <SourcesChart sources={sources} />
            </section>

            <section className="mb-5">
                <div className="d-flex justify-content-between align-items-baseline">
                    <h2 className="h5">Monthly Activity (last 12 months)</h2>
                    <Link href="/usage/months" className="small">
                        View all months
                    </Link>
                </div>
                <MonthlyTable monthly={monthly} />
            </section>

            <section className="mb-5">
                <div className="d-flex justify-content-between align-items-baseline">
                    <h2 className="h5">Top Feeds (all time)</h2>
                    <Link href="/usage/feeds" className="small">
                        View all feeds
                    </Link>
                </div>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Feed</th>
                                <th>Source</th>
                                <th className="num">PDFs</th>
                                <th>Last 12 Months</th>
                                <th className="num">Meetings</th>
                                <th>Last Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topFeeds.map((row) => (
                                <tr key={row.fingerprint}>
                                    <td>
                                        <FeedName feed={row} />
                                    </td>
                                    <td>
                                        <SourcePill sourceType={row.source_type} />
                                    </td>
                                    <td className="num">{row.pdfs}</td>
                                    <td>
                                        <Sparkline values={row.trend} />
                                    </td>
                                    <td className="num">{row.meetings ?? '—'}</td>
                                    <td>{row.last_used}</td>
                                </tr>
                            ))}
                            {topFeeds.length === 0 && (
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

            <section className="mb-5">
                <h2 className="h5">Top Referrers (all time)</h2>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Host</th>
                                <th className="num">Forms Opened</th>
                                <th className="num">PDFs</th>
                            </tr>
                        </thead>
                        <tbody>
                            {topReferrers.map((row) => (
                                <tr key={row.host}>
                                    <td>{row.host}</td>
                                    <td className="num">{row.forms_opened}</td>
                                    <td className="num">{row.pdfs}</td>
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
                <h2 className="h5">Failures (all time)</h2>
                <p className="text-muted small mb-2">{failureSummary(failures)}</p>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Outcome</th>
                                <th>What it means</th>
                                <th className="num">Feed Status</th>
                                <th className="num">Count</th>
                                <th className="num">Share of Requests</th>
                            </tr>
                        </thead>
                        <tbody>
                            {failures.rows.map((row) => (
                                <tr key={`${row.outcome}-${row.upstream_status}`}>
                                    <td>
                                        <OutcomePill outcome={row.outcome} />
                                    </td>
                                    <td className="text-muted">{failureMeaning(row)}</td>
                                    <td className="num">{row.upstream_status ?? '—'}</td>
                                    <td className="num">{row.count}</td>
                                    <td className="num">{percent(row.count, failures.requests)}</td>
                                </tr>
                            ))}
                            {failures.rows.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="text-muted">
                                        No failures.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mb-5">
                <h2 className="h5">Settings (last 90 days)</h2>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th className="num">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {settingGroups.map((group) =>
                                group.values.map((row, i) => (
                                    <tr key={`${group.setting}-${row.value}`}>
                                        {i === 0 && (
                                            <td rowSpan={group.values.length}>{group.setting}</td>
                                        )}
                                        <td>
                                            <Pill>{row.value}</Pill>
                                        </td>
                                        <td className="num">{row.count}</td>
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
                <h2 className="h5">Heaviest Requests (last 90 days)</h2>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Feed</th>
                                <th className="num">Meetings</th>
                                <th>Chunked</th>
                                <th className="num">Duration (ms)</th>
                                <th className="num">Peak Memory (MB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {heaviest.map((row, i) => (
                                <tr key={i}>
                                    <td>{row.when}</td>
                                    <td>{row.label_or_host || '—'}</td>
                                    <td className="num">{row.meeting_count ?? '—'}</td>
                                    <td>{row.chunked ? 'Yes' : 'No'}</td>
                                    <td className="num">{row.duration_ms}</td>
                                    <td className="num">{row.peak_memory_mb}</td>
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
