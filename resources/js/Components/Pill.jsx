import { sourceTypeLabel } from '../sourceTypes';

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

export function SourcePill({ sourceType }) {
    return <Pill>{sourceTypeLabel(sourceType)}</Pill>;
}

/** Outcomes: green for success, red for crashes, amber for the handled failures (bad feed, bad cover). */
export function OutcomePill({ outcome }) {
    return <Pill dot={OUTCOME_COLOURS[outcome] ?? 'var(--bs-warning)'}>{outcome.replace('_', ' ')}</Pill>;
}
