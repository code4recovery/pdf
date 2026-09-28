/**
 * Month-by-month activity, newest first, with a bar showing each month's PDFs against the busiest month.
 */
export default function MonthlyTable({ monthly }) {
    const maxMonthlyPdfs = Math.max(1, ...monthly.map((row) => row.pdfs));

    return (
        <div className="table-responsive">
            <table className="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>PDFs</th>
                        <th style={{ width: '35%' }}></th>
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
                            <td>
                                <div
                                    className="bg-primary"
                                    style={{
                                        height: '0.6rem',
                                        width: `${(row.pdfs / maxMonthlyPdfs) * 100}%`,
                                    }}
                                ></div>
                            </td>
                            <td>{row.forms_opened}</td>
                            <td>{row.failures}</td>
                            <td>{row.unique_feeds}</td>
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
