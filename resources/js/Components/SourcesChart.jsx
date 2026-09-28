import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { sourceTypeLabel } from '../sourceTypes';

const COLOURS = {
    tsml: 'var(--bs-primary)',
    google_sheet: 'var(--bs-success)',
    json: 'var(--bs-warning)',
};

/**
 * Share of successful PDFs by feed source, as a donut with a legend of PDF and feed counts.
 */
export default function SourcesChart({ sources }) {
    const total = sources.reduce((sum, row) => sum + row.pdfs, 0);

    if (total === 0) {
        return <p className="text-muted mb-0">No activity yet.</p>;
    }

    return (
        <div className="d-flex flex-wrap align-items-center gap-4">
            <div style={{ width: 160, height: 160 }}>
                <ResponsiveContainer>
                    <PieChart>
                        <Pie
                            data={sources}
                            dataKey="pdfs"
                            nameKey="source_type"
                            innerRadius={48}
                            outerRadius={76}
                            stroke="var(--bs-body-bg)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        >
                            {sources.map((row) => (
                                <Cell key={row.source_type} fill={COLOURS[row.source_type] ?? 'var(--bs-secondary)'} />
                            ))}
                        </Pie>
                        <Tooltip
                            formatter={(value, name) => [`${value} PDFs`, sourceTypeLabel(name)]}
                            contentStyle={{
                                background: 'var(--bs-body-bg)',
                                border: '1px solid var(--bs-border-color)',
                                borderRadius: '0.5rem',
                            }}
                            itemStyle={{ color: 'var(--bs-body-color)' }}
                        />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <ul className="list-unstyled mb-0">
                {sources.map((row) => (
                    <li key={row.source_type} className="d-flex align-items-center gap-2 mb-2">
                        <span
                            className="dash-pill-dot"
                            style={{ background: COLOURS[row.source_type] ?? 'var(--bs-secondary)' }}
                        ></span>
                        <span className="fw-semibold">{sourceTypeLabel(row.source_type)}</span>
                        <span className="text-muted">
                            {Math.round((row.pdfs / total) * 100)}% · {row.pdfs} PDFs · {row.feeds}{' '}
                            {row.feeds === 1 ? 'feed' : 'feeds'}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
