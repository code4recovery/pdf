import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const AXIS_TICK = { fill: 'var(--bs-secondary-color)', fontSize: 12 };

/**
 * PDF requests per month as stacked bars: successful PDFs with failures on top, so each bar's height is
 * every PDF request that month. Oldest month on the left. Forms opened are left out on purpose: many
 * form visits go on to make a PDF, so stacking them would count those requests twice.
 */
export default function MonthlyChart({ monthly }) {
    const data = [...monthly].sort((a, b) => a.month.localeCompare(b.month));

    return (
        <div style={{ width: '100%', height: 260 }} className="mb-3">
            <ResponsiveContainer>
                <BarChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
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
                    <Bar dataKey="pdfs" name="PDFs" stackId="requests" fill="var(--bs-primary)" maxBarSize={48} isAnimationActive={false} />
                    <Bar dataKey="failures" name="Failures" stackId="requests" fill="var(--bs-danger)" maxBarSize={48} isAnimationActive={false} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
