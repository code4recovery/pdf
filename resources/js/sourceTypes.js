const SOURCE_TYPE_LABELS = {
    tsml: '12 Step Meeting List',
    google_sheet: 'Google Sheets',
    json: 'Other JSON',
    none: 'None',
};

export function sourceTypeLabel(sourceType) {
    return SOURCE_TYPE_LABELS[sourceType] || sourceType;
}

const SOURCE_TYPE_COLOURS = {
    tsml: 'var(--bs-primary)',
    google_sheet: 'var(--bs-success)',
    json: 'var(--bs-warning)',
};

/** The colour that stands for a source everywhere on the dashboard (chart slices, badges). */
export function sourceTypeColour(sourceType) {
    return SOURCE_TYPE_COLOURS[sourceType] || 'var(--bs-secondary)';
}
