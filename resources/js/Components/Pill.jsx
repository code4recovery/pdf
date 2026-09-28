import { sourceTypeLabel, sourceTypeThemeColour } from '../sourceTypes';

const OUTCOME_COLOURS = {
    success: 'var(--bs-success)',
    error: 'var(--bs-danger)',
};

/** A rounded label, optionally with a coloured status dot. */
export function Pill({ children, dot }) {
    return (
        <span className="dash-pill">
            {dot && <span className="dash-pill-dot" style={{ background: dot }}></span>}
            {children}
        </span>
    );
}

/**
 * A filled badge in the source's chart colour: a tinted background with high-contrast text in the same hue,
 * which stays readable in both light and dark themes (a saturated fill with white text only just passes WCAG AA).
 */
export function SourcePill({ sourceType }) {
    const colour = sourceTypeThemeColour(sourceType);

    return (
        <span className={`dash-pill dash-pill-filled bg-${colour}-subtle text-${colour}-emphasis border-${colour}-subtle`}>
            {sourceTypeLabel(sourceType)}
        </span>
    );
}

/** Outcomes: green for success, red for crashes, amber for the handled failures (bad feed, bad cover). */
export function OutcomePill({ outcome }) {
    return <Pill dot={OUTCOME_COLOURS[outcome] ?? 'var(--bs-warning)'}>{outcome.replace('_', ' ')}</Pill>;
}
