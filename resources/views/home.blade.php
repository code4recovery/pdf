<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous" />
    <title>{{ __('c4r.pdf.header.title') }}</title>
</head>

<body class="bg-light">
    <main class="container-md my-5">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                {!! Form::open(['url' => 'pdf', 'method' => 'get']) !!}
                <h1>{{ __('c4r.pdf.home.title') }}</h1>
                <p class="lead">{{ __('c4r.pdf.home.subtitle') }}</p>
                @if (session('error'))
                    <p class="alert alert-danger">
                        {{ session('error') }}
                    </p>
                @endif
                <div class="row">
                    <div class="col-12 mb-4">
                        <label class="form-label fw-bold" for="json">
                            {{ __('c4r.pdf.home.label.url') }}
                        </label>
                        {!! Form::url('json', old('json', $json), [
    'class' => 'form-control',
    'id' => 'json',
    'required' => true,
]) !!}
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="width" class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.width') }}
                        </label>
                        {!! Form::number('width', old('width', 4.25), [
    'class' => 'form-control',
    'id' => 'width',
    'required' => true,
    'step' => '0.01',
]) !!}
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="height" class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.height') }}
                        </label>
                        {!! Form::number('height', old('height', 11), [
    'class' => 'form-control',
    'id' => 'height',
    'required' => true,
    'step' => '0.01',
]) !!}
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="numbering" class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.start') }}
                        </label>
                        {!! Form::number('numbering', old('numbering', 1), [
    'class' => 'form-control',
    'id' => 'numbering',
]) !!}
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold" for="type">
                            {{ __('c4r.pdf.home.label.type') }}
                        </label>
                        {!! Form::select('type', ['' => __('c4r.pdf.home.label.any_type')] + $types, old('type', ''), ['id' => 'type', 'class' => 'form-select']) !!}
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">Language</label>
                        @foreach ($languages as $language => $label)
                            <div class="form-check">
                                {!! Form::radio('language', $language, $language === old('language', 'en'), ['id' => 'language-' . $language, 'class' => 'form-check-input']) !!}
                                <label class="form-check-label" for="language-{{ $language }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.font') }}
                        </label>
                        @foreach ($fonts as $font => $label)
                            <div class="form-check">
                                {!! Form::radio('font', $font, $font === old('font', 'sans-serif'), ['id' => 'font-' . $font, 'class' => 'form-check-input']) !!}
                                <label class="form-check-label" for="font-{{ $font }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.group_by') }}
                        </label>
                        @foreach ($group_by as $group => $label)
                            <div class="form-check-label" class="form-check">
                                {!! Form::radio('group_by', $group, $group === old('group_by', 'day-region'), ['id' => 'group_by-' . $group, 'class' => 'form-check-input']) !!}
                                <label for="group_by-{{ $group }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.mode') }}
                        </label>
                        @foreach ($modes as $mode => $label)
                            <div class="form-check">
                                {!! Form::radio('mode', $mode, $mode === old('mode', 'download'), ['id' => 'mode-' . $mode, 'class' => 'form-check-input']) !!}
                                <label class="form-check-label" for="mode-{{ $mode }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold">
                            {{ __('c4r.pdf.home.label.options') }}
                        </label>
                        @foreach ($options as $option => $label)
                            <div class="form-check">
                                {!! Form::checkbox('options[]', $option, in_array($option, old('options', [])), ['id' => 'option-' . $option, 'class' => 'form-check-input']) !!}
                                <label class="form-check-label" for="option-{{ $option }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-12 text-center my-4">
                        {!! Form::submit(__('c4r.pdf.home.label.generate'), [
    'class' => 'btn btn-primary btn-lg px-4',
]) !!}
                    </div>
                </div>
                {!! Form::close() !!}
                <p class="mb-4 mt-5">
                    {!! __('c4r.pdf.home.label.more_info') !!}
                </p>
                <p class="text-center">
                    <a href="https://code4recovery.org" target="_blank">
                        <img src="/logo.svg" width="100" height="100" alt="Code for Recovery" />
                    </a>
                </p>
            </div>
        </div>
    </main>
</body>

</html>
