import { Link, router } from '@inertiajs/react';
import FeedName from '../Components/FeedName';
import MonthlyTable from '../Components/MonthlyTable';
import { OutcomePill, Pill, SourcePill } from '../Components/Pill';
import '../../css/dashboard.css';

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
                <h1 className="h3 mb-0">PDF Usage Dashboard</h1>
                <form onSubmit={handleSignOut}>
                    <button type="submit" className="btn btn-outline-secondary btn-sm">
                        Sign out
                    </button>
                </form>
            </div>

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
                <h2 className="h5">Sources</h2>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th className="num">PDFs</th>
                                <th className="num">Feeds</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sources.map((row) => (
                                <tr key={row.source_type}>
                                    <td>
                                        <SourcePill sourceType={row.source_type} />
                                    </td>
                                    <td className="num">{row.pdfs}</td>
                                    <td className="num">{row.feeds}</td>
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
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Feed</th>
                                <th>Source</th>
                                <th className="num">PDFs</th>
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
                                    <td className="num">{row.meetings ?? '—'}</td>
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
                <h2 className="h5">Outcomes</h2>
                <div className="table-responsive dash-table">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Outcome</th>
                                <th className="num">Upstream Status</th>
                                <th className="num">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            {outcomes.map((row) => (
                                <tr key={`${row.outcome}-${row.upstream_status}`}>
                                    <td>
                                        <OutcomePill outcome={row.outcome} />
                                    </td>
                                    <td className="num">{row.upstream_status ?? '—'}</td>
                                    <td className="num">{row.count}</td>
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
                <h2 className="h5">Heaviest Requests</h2>
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
