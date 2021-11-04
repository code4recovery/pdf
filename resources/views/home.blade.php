<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="{{ mix('css/app.css') }}" rel="stylesheet" />
    <title>PDF Generator</title>
</head>

<body
    class="
            flex
            bg-gradient-to-br
            from-gray-50
            via-gray-100
            to-gray-50
            py-7
            min-h-screen
        ">
    {!! Form::open(['url' => 'pdf', 'method' => 'get', 'class' => 'container mx-auto px-4 max-w-4xl self-center']) !!}
    <h1 class="text-4xl font-bold mb-4">📄 PDF Generator</h1>
    <p class="mb-4 text-lg">
        This service creates inside pages for a printed meeting schedule
        from a Meeting Guide JSON feed. For more info, or to contribute,
        check out the
        <a href="https://github.com/code4recovery/pdf" class="text-blue-600 underline" target="_blank">
            project page on Github</a>.
    </p>
    @if (session('error'))
        <p
            class="
                bg-red-100
                border border-red-200
                mb-4
                px-4
                py-3
                rounded-md
                text-red-900
            ">
            {{ session('error') }}
        </p>
    @endif
    <div class="grid md:grid-cols-12 gap-4">
        <div class="md:col-span-8 mb-3">
            <label class="font-bold block mb-1" for="json">
                JSON Feed
            </label>
            {!! Form::url('json', old('json', $json), [
    'class' => 'rounded-md
                w-full',
    'id' => 'json',
    'required' => true,
]) !!}
        </div>
        <div class="md:col-span-4 mb-3">
            <label class="font-bold block mb-1" for="type">Type</label>
            {!! Form::select('type', ['' => 'Any Type'] + $types, old('type', ''), ['class' => 'rounded-md w-full']) !!}
        </div>
        <div class="md:col-span-4 mb-3">
            <label for="width" class="font-bold block mb-1"> Width </label>
            {!! Form::number('width', old('width', 4.25), [
    'class' => 'rounded-md w-full',
    'id' => 'width',
    'required' => true,
    'step' => '0.01',
]) !!}
        </div>
        <div class="md:col-span-4 mb-3">
            <label for="height" class="font-bold block mb-1">
                Height
            </label>
            {!! Form::number('height', old('height', 11), [
    'class' => 'rounded-md w-full',
    'id' => 'height',
    'required' => true,
    'step' => '0.01',
]) !!}
        </div>
        <div class="md:col-span-4 mb-3">
            <label for="numbering" class="font-bold block mb-1">
                Start #
            </label>
            {!! Form::number('numbering', old('numbering', 1), [
    'class' => 'rounded-md w-full',
    'id' => 'numbering',
    'required' => true,
]) !!}
        </div>
        <div class="grid gap-1 content-start md:col-span-3 mb-3">
            <label class="font-bold block">Language</label>
            @foreach ($languages as $language => $label)
                <div class="flex items-center gap-2">
                    {!! Form::radio('language', $language, $language === old('language', 'en'), ['id' => 'language-' . $language]) !!}
                    <label for="language-{{ $language }}">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
        <div class="grid gap-1 content-start md:col-span-3 mb-3">
            <label class="font-bold block">Font</label>
            @foreach ($fonts as $font => $label)
                <div class="flex items-center gap-2">
                    {!! Form::radio('font', $font, $font === old('font', 'sans-serif'), ['id' => 'font-' . $font]) !!}
                    <label for="font-{{ $font }}">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
        <div class="grid gap-1 content-start md:col-span-3 mb-3">
            <label class="font-bold block">Group by</label>
            @foreach ($group_by as $group => $label)
                <div class="flex items-center gap-2">
                    {!! Form::radio('group_by', $group, $group === old('group_by', 'day-region'), ['id' => 'group_by-' . $group]) !!}
                    <label for="group_by-{{ $group }}">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
        <div class="grid gap-1 content-start md:col-span-3 mb-3">
            <label class="font-bold block">Mode</label>
            @foreach ($modes as $mode => $label)
                <div class="flex items-center gap-2">
                    {!! Form::radio('mode', $mode, $mode === old('mode', 'download'), ['id' => 'mode-' . $mode]) !!}
                    <label for="mode-{{ $mode }}">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
        <div class="col-span-full text-center">
            {!! Form::submit('⚡️ Generate ⚡️', [
    'class' => 'bg-blue-600
                cursor-pointer px-5 py-3 rounded-md text-white text-xl',
]) !!}
        </div>
    </div>
    {!! Form::close() !!}
</body>

</html>
