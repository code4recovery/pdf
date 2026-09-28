const SOURCE_TYPE_LABELS = {
    tsml: 'TSML',
    google_sheet: 'Google Sheets',
    json: 'Other JSON',
    none: 'None',
};

export function sourceTypeLabel(sourceType) {
    return SOURCE_TYPE_LABELS[sourceType] || sourceType;
}

const SOURCE_TYPE_THEME_COLOURS = {
    tsml: 'primary',
    google_sheet: 'success',
    json: 'warning',
};

/** The Bootstrap theme colour name that stands for a source (e.g. 'primary'). */
export function sourceTypeThemeColour(sourceType) {
    return SOURCE_TYPE_THEME_COLOURS[sourceType] || 'secondary';
}

/** The colour that stands for a source everywhere on the dashboard (chart slices, badges). */
export function sourceTypeColour(sourceType) {
    return `var(--bs-${sourceTypeThemeColour(sourceType)})`;
}
