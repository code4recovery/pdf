import { sourceTypeLabel, sourceTypeThemeColour } from '../sourceTypes';

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
