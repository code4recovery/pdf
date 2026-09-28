import { Line, LineChart, YAxis } from 'recharts';

/**
 * A tiny line of monthly PDF counts (oldest first) with no axes, for use inside a table cell.
 */
export default function Sparkline({ values, width = 120, height = 28 }) {
    const data = values.map((pdfs, i) => ({ i, pdfs }));
    const summary = `PDFs per month, last ${values.length} months: ${values.join(', ')}`;

    return (
        <div role="img" aria-label={summary} title={summary} style={{ width, height }}>
            <LineChart width={width} height={height} data={data} margin={{ top: 3, right: 3, bottom: 3, left: 3 }}>
                <YAxis hide domain={[0, 'dataMax']} />
                <Line
                    type="monotone"
                    dataKey="pdfs"
                    stroke="var(--bs-primary)"
                    strokeWidth={1.5}
                    dot={false}
                    isAnimationActive={false}
                />
            </LineChart>
        </div>
    );
}
