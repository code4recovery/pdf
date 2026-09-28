const SOURCE_TYPE_LABELS = {
    tsml: '12 Step Meeting List',
    google_sheet: 'Google Sheets',
    json: 'Other JSON',
    none: 'None',
};

export function sourceTypeLabel(sourceType) {
    return SOURCE_TYPE_LABELS[sourceType] || sourceType;
}
