import { Bar, CartesianGrid, ComposedChart, Legend, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const AXIS_TICK = { fill: 'var(--bs-secondary-color)', fontSize: 12 };

/**
 * PDFs per month as bars, with forms opened and failures as lines, oldest month on the left.
 */
export default function MonthlyChart({ monthly }) {
    const data = [...monthly].sort((a, b) => a.month.localeCompare(b.month));

    return (
        <div style={{ width: '100%', height: 260 }} className="mb-3">
            <ResponsiveContainer>
                <ComposedChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
                    <CartesianGrid stroke="var(--bs-border-color)" vertical={false} />
                    <XAxis dataKey="month" tick={AXIS_TICK} stroke="var(--bs-border-color)" />
                    <YAxis allowDecimals={false} tick={AXIS_TICK} stroke="var(--bs-border-color)" />
                    <Tooltip
                        contentStyle={{
                            background: 'var(--bs-body-bg)',
                            border: '1px solid var(--bs-border-color)',
                            color: 'var(--bs-body-color)',
                        }}
                        cursor={{ fill: 'var(--bs-tertiary-bg)' }}
                    />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Bar dataKey="pdfs" name="PDFs" fill="var(--bs-primary)" maxBarSize={48} />
                    <Line dataKey="forms_opened" name="Forms Opened" stroke="var(--bs-info)" strokeWidth={2} dot={false} />
                    <Line dataKey="failures" name="Failures" stroke="var(--bs-danger)" strokeWidth={2} dot={false} />
                </ComposedChart>
            </ResponsiveContainer>
        </div>
    );
}
