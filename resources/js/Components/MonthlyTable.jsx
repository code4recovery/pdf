import '../../css/dashboard.css';

/**
 * Month-by-month activity, newest first, with a bar showing each month's PDFs against the busiest month.
 */
export default function MonthlyTable({ monthly }) {
    const maxMonthlyPdfs = Math.max(1, ...monthly.map((row) => row.pdfs));

    return (
        <div className="table-responsive dash-table">
            <table className="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th className="num">PDFs</th>
                        <th style={{ width: '35%' }}></th>
                        <th className="num">Forms Opened</th>
                        <th className="num">Failures</th>
                        <th className="num">Unique Feeds</th>
                    </tr>
                </thead>
                <tbody>
                    {monthly.map((row) => (
                        <tr key={row.month}>
                            <td>{row.month}</td>
                            <td className="num">{row.pdfs}</td>
                            <td>
                                <div
                                    className="dash-bar"
                                    style={{
                                        width: `${(row.pdfs / maxMonthlyPdfs) * 100}%`,
                                    }}
                                ></div>
                            </td>
                            <td className="num">{row.forms_opened}</td>
                            <td className="num">{row.failures}</td>
                            <td className="num">{row.unique_feeds}</td>
                        </tr>
                    ))}
                    {monthly.length === 0 && (
                        <tr>
                            <td colSpan="6" className="text-muted">
                                No activity yet.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
