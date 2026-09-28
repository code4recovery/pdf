import { Fragment, useState } from 'react';

/** The public form's own label for each recorded setting. */
const SETTING_LABELS = {
    group_by: 'Group by',
    language: 'Language',
    paper: 'Paper size',
    font: 'Font',
    mode: 'Mode',
    front_cover: 'Front cover',
    back_cover: 'Back cover',
    regions_selected: 'Filter by Regions',
};

/** Turns the flat {setting, value, count} rows (already sorted most common first) into one group per setting. */
function groupBySetting(settings) {
    const groups = [];
    const index = {};

    settings.forEach((row) => {
        if (!(row.setting in index)) {
            index[row.setting] = groups.length;
            groups.push({ setting: row.setting, values: [], total: 0 });
        }

        groups[index[row.setting]].values.push(row);
        groups[index[row.setting]].total += row.count;
    });

    return groups;
}

function share(count, total) {
    return total === 0 ? '—' : `${Math.round((count / total) * 100)}%`;
}

/**
 * One row per setting showing its most common value and how many different values were used; clicking a row
 * expands it into one row per value, with that value's share and count lined up under the same columns.
 */
export default function SettingsTable({ settings }) {
    const groups = groupBySetting(settings);
    const [open, setOpen] = useState({});

    const toggle = (setting) => setOpen((current) => ({ ...current, [setting]: !current[setting] }));

    return (
        <div className="table-responsive dash-table">
            <table className="table">
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Most Common</th>
                        <th className="num">Values Used</th>
                        <th className="num">Requests</th>
                    </tr>
                </thead>
                <tbody>
                    {groups.map((group) => {
                        const isOpen = Boolean(open[group.setting]);
                        const top = group.values[0];
                        const panelId = `setting-${group.setting}`;

                        return (
                            <Fragment key={group.setting}>
                                <tr>
                                    <td>
                                        <button
                                            type="button"
                                            className="btn btn-link p-0 text-reset text-decoration-none d-inline-flex align-items-center gap-2"
                                            aria-expanded={isOpen}
                                            aria-controls={group.values.map((row) => `${panelId}-${row.value}`).join(' ')}
                                            onClick={() => toggle(group.setting)}
                                        >
                                            <span aria-hidden="true" className="dash-chevron">
                                                {isOpen ? '▾' : '▸'}
                                            </span>
                                            {SETTING_LABELS[group.setting] ?? group.setting}
                                        </button>
                                    </td>
                                    <td>
                                        {top.value} <span className="text-muted">({share(top.count, group.total)})</span>
                                    </td>
                                    <td className="num">{group.values.filter((row) => row.count > 0).length}</td>
                                    <td className="num">{group.total}</td>
                                </tr>
                                {isOpen &&
                                    group.values.map((row) => (
                                        <tr key={row.value} id={`${panelId}-${row.value}`} className="dash-subrow">
                                            <td></td>
                                            <td>{row.value}</td>
                                            <td className="num">{share(row.count, group.total)}</td>
                                            <td className="num">{row.count}</td>
                                        </tr>
                                    ))}
                            </Fragment>
                        );
                    })}
                    {groups.length === 0 && (
                        <tr>
                            <td colSpan="4" className="text-muted">
                                No activity yet.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
