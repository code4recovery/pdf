<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
            crossorigin="anonymous"
        />
        <title>PDF Generator</title>
    </head>
    <body>
        <div class="container py-5">
            <div class="row">
                <form method="get" action="/" class="col-lg-8 offset-lg-2">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h1>📄 PDF Generator</h1>
                            <p>
                                This service creates inside pages for a printed
                                meeting schedule from a Meeting Guide JSON feed.
                                For more info, or to contribute, check out the
                                <a href="https://github.com/code4recovery/pdf"
                                    >project page on Github</a
                                >.
                            </p>
                        </div>
                        @if (session('error'))
                        <div class="col-12 mb-3">
                            <div class="alert alert-danger">
                                {{ session("error") }}
                            </div>
                        </div>
                        @endif
                        <div class="col-12 mb-3">
                            <label for="json" class="form-label"
                                >JSON Feed</label
                            >
                            <input
                                class="form-control"
                                name="json"
                                id="json"
                                type="url"
                                value="{{
                                    old(
                                        'json',
                                        'https://demo.code4recovery.org/wp-admin/admin-ajax.php?action=meetings'
                                    )
                                }}"
                                required
                            />
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label for="width" class="form-label">Width</label>
                            <input
                                class="form-control"
                                id="width"
                                name="width"
                                required
                                step="0.01"
                                type="number"
                                value="{{ old('width', 4.25) }}"
                            />
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label for="height" class="form-label"
                                >Height</label
                            >
                            <input
                                class="form-control"
                                id="height"
                                name="height"
                                required
                                step="0.01"
                                type="number"
                                value="{{ old('height', 11) }}"
                            />
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label for="numbering" class="form-label"
                                >Start #</label
                            >
                            <input
                                class="form-control"
                                id="numbering"
                                name="numbering"
                                required
                                type="number"
                                value="{{ old('numbering', 1) }}"
                            />
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Font</label>
                            @foreach ($fonts as $font => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                name="font" value="{{ $font }}" id="font-{{
                                    $font
                                }}" @if ($font === old('font', 'sans-serif'))
                                checked @endif />
                                <label
                                    class="form-check-label"
                                    for="font-{{ $font }}"
                                >
                                    {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Mode</label>
                            @foreach ($modes as $mode => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                name="mode" value="{{ $mode }}" id="mode-{{
                                    $mode
                                }}" @if ($mode === old('mode', 'download'))
                                checked @endif />
                                <label
                                    class="form-check-label"
                                    for="mode-{{ $mode }}"
                                >
                                    {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="col-12 text-center mt-3">
                            <input
                                type="submit"
                                class="btn btn-primary btn-lg"
                                value=" ⚡️ Generate ⚡️ "
                            />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
