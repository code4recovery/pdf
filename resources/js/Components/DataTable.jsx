import {
    columnFilteringFeature,
    createFilteredRowModel,
    createSortedRowModel,
    filterFn_includesString,
    globalFilteringFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';

const features = tableFeatures({
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
    columnFilteringFeature,
    globalFilteringFeature,
    filteredRowModel: createFilteredRowModel(),
    filterFns: { includesString: filterFn_includesString },
});

const SORT_ARROWS = { asc: ' ▲', desc: ' ▼' };

/**
 * A Bootstrap-styled table with click-to-sort headers and an optional search box.
 *
 * `columns` are TanStack column definitions; `data` must be a stable array (a prop or memoised value).
 */
export default function DataTable({ columns, data, searchPlaceholder, emptyMessage = 'No activity yet.' }) {
    const table = useTable({
        features,
        columns,
        data,
        globalFilterFn: 'includesString',
        enableSortingRemoval: false,
    });

    const rows = table.getRowModel().rows;
    const columnCount = table.getAllLeafColumns().length;

    return (
        <>
            {searchPlaceholder && (
                <input
                    type="search"
                    className="form-control form-control-sm mb-3"
                    style={{ maxWidth: '20rem' }}
                    placeholder={searchPlaceholder}
                    aria-label={searchPlaceholder}
                    value={table.state.globalFilter ?? ''}
                    onChange={(e) => table.setGlobalFilter(e.target.value)}
                />
            )}
            <div className="table-responsive">
                <table className="table table-sm">
                    <thead>
                        {table.getHeaderGroups().map((group) => (
                            <tr key={group.id}>
                                {group.headers.map((header) => {
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <th
                                            key={header.id}
                                            aria-sort={sorted === 'asc' ? 'ascending' : sorted === 'desc' ? 'descending' : undefined}
                                        >
                                            {header.column.getCanSort() ? (
                                                <button
                                                    type="button"
                                                    className="btn btn-link p-0 fw-bold text-reset text-decoration-none"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                >
                                                    <table.FlexRender header={header} />
                                                    {SORT_ARROWS[sorted] ?? ''}
                                                </button>
                                            ) : (
                                                <table.FlexRender header={header} />
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                {row.getAllCells().map((cell) => (
                                    <td key={cell.id}>
                                        <table.FlexRender cell={cell} />
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={columnCount} className="text-muted">
                                    {data.length === 0 ? emptyMessage : 'No matches.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
