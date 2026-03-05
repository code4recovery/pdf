import { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';

export default function Home({
    screen,
    error: serverError,
    json: initialJson,
    availableRegions,
    fonts,
    font_sizes,
    modes,
    options: optionDefs,
    languages,
    types,
    group_by: groupByOptions,
    width: defaultWidth,
    height: defaultHeight,
    numbering: defaultNumbering,
}) {
    // Screen 1 state
    const [url, setUrl] = useState(initialJson || '');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(serverError || null);

    // Screen 2 form state
    const [width, setWidth] = useState(defaultWidth || 4.25);
    const [height, setHeight] = useState(defaultHeight || 11);
    const [numbering, setNumbering] = useState(defaultNumbering || 1);
    const [type, setType] = useState('');
    const [language, setLanguage] = useState('en');
    const [font, setFont] = useState('sans-serif');
    const [fontSize, setFontSize] = useState('12');
    const [groupBy, setGroupBy] = useState('day-region');
    const [mode, setMode] = useState('download');
    const [selectedOptions, setSelectedOptions] = useState(() => {
        if (!optionDefs) return [];
        return Object.entries(optionDefs)
            .filter(([, opt]) => opt.checked)
            .map(([key]) => key);
    });

    // Region state
    const [selectedRegions, setSelectedRegions] = useState(availableRegions || []);
    const [regionsOpen, setRegionsOpen] = useState(false);

    // PDF generation state
    const [generating, setGenerating] = useState(false);
    const [pdfError, setPdfError] = useState(null);

    // Update state when props change (Inertia navigation)
    useEffect(() => {
        setError(serverError || null);
    }, [serverError]);

    useEffect(() => {
        if (availableRegions) {
            setSelectedRegions(availableRegions);
        }
    }, [availableRegions]);

    // Screen 1: Submit URL
    function handleUrlSubmit(e) {
        e.preventDefault();
        setError(null);
        router.get('/', { json: url }, {
            preserveState: false,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    // Screen 2: Generate PDF
    function handleGenerate(e) {
        e.preventDefault();
        setPdfError(null);

        const params = new URLSearchParams();
        params.set('json', initialJson);
        params.set('width', width);
        params.set('height', height);
        if (numbering) params.set('numbering', numbering);
        params.set('language', language);
        params.set('font', font);
        params.set('font_size', fontSize);
        params.set('group_by', groupBy);
        params.set('mode', mode);
        if (type) params.set('type', type);
        selectedOptions.forEach((opt) => params.append('options[]', opt));
        selectedRegions.forEach((r) => params.append('regions[]', r));

        const pdfUrl = `/pdf?${params.toString()}`;

        if (mode === 'stream') {
            window.open(pdfUrl, '_blank');
            return;
        }

        // Download mode: fetch as blob with spinner
        setGenerating(true);
        fetch(pdfUrl)
            .then((res) => {
                if (!res.ok) {
                    return res.text().then((text) => {
                        throw new Error(text || 'PDF generation failed');
                    });
                }
                return res.blob().then((blob) => {
                    const disposition = res.headers.get('Content-Disposition');
                    let filename = 'directory.pdf';
                    if (disposition) {
                        const match = disposition.match(/filename="?(.+?)"?$/);
                        if (match) filename = match[1];
                    }
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = filename;
                    a.click();
                    URL.revokeObjectURL(a.href);
                });
            })
            .catch((err) => {
                setPdfError(err.message);
            })
            .finally(() => {
                setGenerating(false);
            });
    }

    // Option checkbox toggle
    function toggleOption(key) {
        setSelectedOptions((prev) =>
            prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]
        );
    }

    return (
        <main className="container-md my-5">
            <div className="row">
                <div className="col-md-6 offset-md-3">
                    <h1>PDF Generator</h1>
                    <p className="lead">
                        This service creates inside pages for a printed meeting schedule
                        from a Meeting Guide JSON feed or Google Sheet.
                    </p>

                    {error && (
                        <p className="alert alert-danger">{error}</p>
                    )}
                    {pdfError && (
                        <p className="alert alert-danger">{pdfError}</p>
                    )}

                    {screen === 1 ? (
                        <Screen1
                            url={url}
                            setUrl={setUrl}
                            loading={loading}
                            onSubmit={handleUrlSubmit}
                        />
                    ) : (
                        <Screen2
                            onSubmit={handleGenerate}
                            generating={generating}
                            width={width}
                            setWidth={setWidth}
                            height={height}
                            setHeight={setHeight}
                            numbering={numbering}
                            setNumbering={setNumbering}
                            type={type}
                            setType={setType}
                            types={types}
                            language={language}
                            setLanguage={setLanguage}
                            languages={languages}
                            font={font}
                            setFont={setFont}
                            fonts={fonts}
                            fontSize={fontSize}
                            setFontSize={setFontSize}
                            fontSizes={font_sizes}
                            groupBy={groupBy}
                            setGroupBy={setGroupBy}
                            groupByOptions={groupByOptions}
                            mode={mode}
                            setMode={setMode}
                            modes={modes}
                            selectedOptions={selectedOptions}
                            toggleOption={toggleOption}
                            optionDefs={optionDefs}
                            availableRegions={availableRegions}
                            selectedRegions={selectedRegions}
                            setSelectedRegions={setSelectedRegions}
                            regionsOpen={regionsOpen}
                            setRegionsOpen={setRegionsOpen}
                        />
                    )}

                    <p className="mb-4 mt-5">
                        More information is available on the{' '}
                        <a href="https://github.com/code4recovery/pdf" target="_blank" rel="noreferrer">
                            project page on Github
                        </a>
                        . To get help, please{' '}
                        <a href="https://github.com/code4recovery/pdf/issues" target="_blank" rel="noreferrer">
                            file an issue
                        </a>
                        .
                    </p>
                    <p className="text-center">
                        <a href="https://code4recovery.org" target="_blank" rel="noreferrer">
                            <img src="/logo.svg" width="100" height="100" alt="Code for Recovery" />
                        </a>
                    </p>
                </div>
            </div>
        </main>
    );
}

function Screen1({ url, setUrl, loading, onSubmit }) {
    return (
        <form onSubmit={onSubmit} acceptCharset="UTF-8">
            <div className="mb-4">
                <label className="form-label fw-bold" htmlFor="json">
                    Feed or Sheet URL
                </label>
                <input
                    type="url"
                    name="json"
                    id="json"
                    className="form-control"
                    placeholder="https://example.org/wp-admin/admin-ajax.php?action=meetings"
                    required
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                />
                <div className="form-text">
                    Enter a Meeting Guide JSON feed URL or Google Sheets URL to continue.
                </div>
            </div>
            <div className="text-center my-4">
                <button type="submit" className="btn btn-primary btn-lg px-4" disabled={loading}>
                    {loading ? (
                        <>
                            <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Loading…
                        </>
                    ) : (
                        'Continue'
                    )}
                </button>
            </div>
        </form>
    );
}

function Screen2({
    onSubmit,
    generating,
    width, setWidth,
    height, setHeight,
    numbering, setNumbering,
    type, setType, types,
    language, setLanguage, languages,
    font, setFont, fonts,
    fontSize, setFontSize, fontSizes,
    groupBy, setGroupBy, groupByOptions,
    mode, setMode, modes,
    selectedOptions, toggleOption, optionDefs,
    availableRegions, selectedRegions, setSelectedRegions,
    regionsOpen, setRegionsOpen,
}) {
    return (
        <form onSubmit={onSubmit} acceptCharset="UTF-8">
            <div className="mb-3">
                <a href="/" className="btn btn-outline-secondary btn-sm">&larr; Change URL</a>
            </div>

            <div className="row">
                <div className="col-md-6 mb-4">
                    <label htmlFor="width" className="form-label fw-bold">Width</label>
                    <input
                        type="number"
                        step="0.01"
                        required
                        id="width"
                        className="form-control"
                        value={width}
                        onChange={(e) => setWidth(e.target.value)}
                    />
                </div>
                <div className="col-md-6 mb-4">
                    <label htmlFor="height" className="form-label fw-bold">Height</label>
                    <input
                        type="number"
                        step="0.01"
                        required
                        id="height"
                        className="form-control"
                        value={height}
                        onChange={(e) => setHeight(e.target.value)}
                    />
                </div>
                <div className="col-md-6 mb-4">
                    <label htmlFor="numbering" className="form-label fw-bold">Start #</label>
                    <input
                        type="number"
                        id="numbering"
                        className="form-control"
                        value={numbering}
                        onChange={(e) => setNumbering(e.target.value)}
                    />
                </div>
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold" htmlFor="type">Type</label>
                    <select
                        id="type"
                        className="form-select"
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                    >
                        <option value="">Any Type</option>
                        {Object.entries(types).map(([code, label]) => (
                            <option key={code} value={code}>{label}</option>
                        ))}
                    </select>
                </div>

                {/* Language radios */}
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold">Language</label>
                    {Object.entries(languages).map(([code, label]) => (
                        <div className="form-check" key={code}>
                            <input
                                type="radio"
                                className="form-check-input"
                                name="language"
                                id={`language-${code}`}
                                value={code}
                                checked={language === code}
                                onChange={(e) => setLanguage(e.target.value)}
                            />
                            <label className="form-check-label" htmlFor={`language-${code}`}>
                                {label}
                            </label>
                        </div>
                    ))}
                </div>

                {/* Font radios + font size */}
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold">Font</label>
                    {Object.entries(fonts).map(([code, label]) => (
                        <div className="form-check" key={code}>
                            <input
                                type="radio"
                                className="form-check-input"
                                name="font"
                                id={`font-${code}`}
                                value={code}
                                checked={font === code}
                                onChange={(e) => setFont(e.target.value)}
                            />
                            <label className="form-check-label" htmlFor={`font-${code}`}>
                                {label}
                            </label>
                        </div>
                    ))}
                    <label htmlFor="font_size" className="form-label fw-bold mt-4">Font Size</label>
                    <select
                        id="font_size"
                        className="form-select"
                        value={fontSize}
                        onChange={(e) => setFontSize(e.target.value)}
                    >
                        {Object.entries(fontSizes).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </div>

                {/* Group by radios */}
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold">Group by</label>
                    {Object.entries(groupByOptions).map(([code, label]) => (
                        <div className="form-check" key={code}>
                            <input
                                type="radio"
                                className="form-check-input"
                                name="group_by"
                                id={`group_by-${code}`}
                                value={code}
                                checked={groupBy === code}
                                onChange={(e) => setGroupBy(e.target.value)}
                            />
                            <label className="form-check-label" htmlFor={`group_by-${code}`}>
                                {label}
                            </label>
                        </div>
                    ))}
                </div>

                {/* Mode radios */}
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold">Mode</label>
                    {Object.entries(modes).map(([code, label]) => (
                        <div className="form-check" key={code}>
                            <input
                                type="radio"
                                className="form-check-input"
                                name="mode"
                                id={`mode-${code}`}
                                value={code}
                                checked={mode === code}
                                onChange={(e) => setMode(e.target.value)}
                            />
                            <label className="form-check-label" htmlFor={`mode-${code}`}>
                                {label}
                            </label>
                        </div>
                    ))}
                </div>

                {/* Options checkboxes */}
                <div className="col-md-6 mb-4">
                    <label className="form-label fw-bold">Options</label>
                    {Object.entries(optionDefs).map(([key, opt]) => (
                        <div className="form-check" key={key}>
                            <input
                                type="checkbox"
                                className="form-check-input"
                                id={`option-${key}`}
                                checked={selectedOptions.includes(key)}
                                onChange={() => toggleOption(key)}
                            />
                            <label className="form-check-label" htmlFor={`option-${key}`}>
                                {opt.label}
                            </label>
                        </div>
                    ))}
                </div>

                {/* Regions */}
                {availableRegions && availableRegions.length > 0 && (
                    <RegionFilter
                        availableRegions={availableRegions}
                        selectedRegions={selectedRegions}
                        setSelectedRegions={setSelectedRegions}
                        isOpen={regionsOpen}
                        setIsOpen={setRegionsOpen}
                    />
                )}

                <div className="col-12 text-center my-4">
                    <button type="submit" className="btn btn-primary btn-lg px-4" disabled={generating}>
                        {generating ? (
                            <>
                                <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Generating…
                            </>
                        ) : (
                            'Generate'
                        )}
                    </button>
                </div>
            </div>
        </form>
    );
}

function RegionFilter({ availableRegions, selectedRegions, setSelectedRegions, isOpen, setIsOpen }) {
    // Build region tree from flat list
    const regionTree = availableRegions.map((region) => {
        const parts = region ? region.split(': ') : [''];
        return {
            full: region,
            depth: parts.length - 1,
            label: parts[parts.length - 1] || '(No Region)',
        };
    });

    // Ref map for setting indeterminate
    const checkboxRefs = useRef({});

    const getChildren = useCallback((parentPath) => {
        const prefix = parentPath + ': ';
        return availableRegions.filter((r) => r.startsWith(prefix));
    }, [availableRegions]);

    const getDirectChildren = useCallback((parentPath) => {
        const prefix = parentPath + ': ';
        return availableRegions.filter((r) => {
            if (!r.startsWith(prefix)) return false;
            const remainder = r.substring(prefix.length);
            return !remainder.includes(': ');
        });
    }, [availableRegions]);

    // Update indeterminate states
    useEffect(() => {
        regionTree.forEach(({ full }) => {
            const ref = checkboxRefs.current[full];
            if (!ref) return;

            const children = getChildren(full);
            if (children.length === 0) {
                ref.indeterminate = false;
                return;
            }

            const allChildren = children;
            const checkedCount = allChildren.filter((c) => selectedRegions.includes(c)).length;

            if (checkedCount === 0) {
                ref.indeterminate = false;
            } else if (checkedCount === allChildren.length) {
                ref.indeterminate = false;
            } else {
                ref.indeterminate = true;
            }
        });
    }, [selectedRegions, regionTree, getChildren]);

    function handleToggle(regionPath, checked) {
        const children = getChildren(regionPath);

        setSelectedRegions((prev) => {
            let next = new Set(prev);

            if (checked) {
                next.add(regionPath);
                children.forEach((c) => next.add(c));
            } else {
                next.delete(regionPath);
                children.forEach((c) => next.delete(c));
            }

            // Update parent states up the chain
            updateParents(next, regionPath);

            return [...next];
        });
    }

    function updateParents(regionSet, changedPath) {
        const parts = changedPath.split(': ');
        // Walk up from second-to-last to root
        for (let i = parts.length - 1; i >= 1; i--) {
            const parentPath = parts.slice(0, i).join(': ');
            const directChildren = getDirectChildren(parentPath);

            const allChecked = directChildren.every((c) => regionSet.has(c));
            const someChecked = directChildren.some((c) => regionSet.has(c));

            if (allChecked) {
                regionSet.add(parentPath);
            } else {
                regionSet.delete(parentPath);
            }
        }
    }

    function toggleAll(checked) {
        setSelectedRegions(checked ? [...availableRegions] : []);
    }

    return (
        <div className="col-12 mb-4">
            <button
                className="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center"
                type="button"
                onClick={() => setIsOpen(!isOpen)}
            >
                <span>Filter by Regions</span>
                <span style={{
                    transition: 'transform 0.2s ease',
                    transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
                }}>&#9662;</span>
            </button>
            {isOpen && (
                <div className="card card-body mt-2">
                    <div className="d-flex justify-content-end mb-2">
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary me-2"
                            onClick={() => toggleAll(true)}
                        >
                            Select All
                        </button>
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            onClick={() => toggleAll(false)}
                        >
                            Deselect All
                        </button>
                    </div>
                    <div style={{ maxHeight: '250px', overflowY: 'auto' }}>
                        {regionTree.map(({ full, depth, label }) => (
                            <div
                                className="form-check"
                                key={full}
                                style={{ marginLeft: `${depth * 1.25}rem` }}
                            >
                                <input
                                    type="checkbox"
                                    className="form-check-input"
                                    id={`region-${slugify(full || 'no-region')}`}
                                    ref={(el) => { checkboxRefs.current[full] = el; }}
                                    checked={selectedRegions.includes(full)}
                                    onChange={(e) => handleToggle(full, e.target.checked)}
                                />
                                <label
                                    className="form-check-label"
                                    htmlFor={`region-${slugify(full || 'no-region')}`}
                                >
                                    {label}
                                </label>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function slugify(str) {
    return str
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}
