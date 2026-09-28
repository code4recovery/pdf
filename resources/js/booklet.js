const STANDARD_SHEETS = [
    { name: 'US Letter', width: 8.5, height: 11 },
    { name: 'US Legal', width: 8.5, height: 14 },
    { name: 'Tabloid', width: 11, height: 17 },
    { name: 'A4', width: 8.27, height: 11.69 },
    { name: 'A3', width: 11.69, height: 16.54 },
];

const TOLERANCE_INCHES = 0.125;

/**
 * The sheet a booklet of the given page size prints on: twice the page width by the
 * same height, in inches. Returns null when either value is not a positive number.
 */
export function bookletSheet(width, height) {
    const pageWidth = Number(width);
    const pageHeight = Number(height);

    if (width === '' || height === '' || !Number.isFinite(pageWidth) || !Number.isFinite(pageHeight) || pageWidth <= 0 || pageHeight <= 0) {
        return null;
    }

    const sheetWidth = Math.round(pageWidth * 2 * 100) / 100;
    const sheetHeight = Math.round(pageHeight * 100) / 100;
    const fits = (a, b) => Math.abs(a - b) <= TOLERANCE_INCHES;
    const standard = STANDARD_SHEETS.find(
        (s) => (fits(sheetWidth, s.width) && fits(sheetHeight, s.height))
            || (fits(sheetWidth, s.height) && fits(sheetHeight, s.width))
    );

    let flip = null;
    if (sheetHeight > sheetWidth) flip = 'long';
    if (sheetWidth > sheetHeight) flip = 'short';

    return { width: sheetWidth, height: sheetHeight, name: standard ? standard.name : null, flip };
}
