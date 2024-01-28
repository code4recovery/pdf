@php
    $page_margin = 18;
    $footer_height = 20;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&family=Zen+Old+Mincho:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: {{ $page_margin }}px;

            @if ($numbering !== false)
                margin-bottom: {{ $footer_height + $page_margin }}px;
            @endif
        }

        body {
            color: black;
            counter-increment: page 1;
            counter-reset: page {{ $numbering - 1 }};
            font-family: {{ $font }};
            font-size: 12px;
        }

        .heading {
            font-weight: bold;
            display: block;
            border-bottom: 0.5px solid black;
            font-size: 16px;
            margin: 0 0 10px;
            padding-bottom: 4px;
        }

        .subheading {
            display: block;
            font-weight: normal;
            font-size: 11px;
            margin: 1px 0 3px;
            page-break-after: avoid;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .legend {
            page-break-after: always;
        }

        @if (in_array('pagebreaks', $options))@if ($group_by === 'region-day') .region @else.day @endif{
            page-break-after: always;
        }@endif


        .legend>div {
            font-size: 9px;
            border-bottom: .5px solid #ddd;
            padding-bottom: 1.5px;
            padding-top: 6px;
        }

        .legend>div:last-child {
            border-bottom: none;
        }

        .legend>div span {
            display: inline-block;
        }

        .legend>div .type {
            width: 40px;
        }

        .day:last-child {
            page-break-after: auto;
        }

        .meeting {
            border-spacing: 0;
            margin: 0 0 5px;
            padding: 0;
            page-break-inside: avoid;
            vertical-align: top;
            width: 100%;
        }

        .meeting td {
            margin: 0;
            padding: 0;
            vertical-align: top;
        }

        .meeting .time,
        .meeting .types {
            width: 65px;
        }

        .meeting .types {
            text-align: right;
        }

        .meeting .name {
            font-weight: bold;
        }

        footer {
            bottom: -{{ $footer_height }}px;
            height: {{ $footer_height }}px;
            left: 0;
            position: fixed;
            right: 0;
        }

        footer::after {
            border-top: 0.5px solid black;
            content: counter(page);
            left: 50%;
            margin-left: -20px;
            padding-top: 4px;
            position: absolute;
            text-align: center;
            width: 40px;
        }
    </style>
</head>

<body>
    @if ($numbering !== false)
        <footer></footer>
    @endif
    <main>
        @if (in_array('legend', $options))
            @include('legend', compact('types_in_use', 'types'))
        @endif
        @if ($group_by === 'day-region')
            @foreach ($days as $day => $regions)
                <div class="day">
                    <span class="heading">{{ $day }}</span>
                    @foreach ($regions as $region => $meetings)
                        <div class="region">
                            @if ($region)
                                <span class="subheading">{{ $region }}</span>
                            @endif
                            @foreach ($meetings as $meeting)
                                @include('meeting', compact('meeting', 'region'))
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        @elseif ($group_by === 'region-day')
            @foreach ($regions as $region => $days)
                <div class="region">
                    <span class="heading">{{ $region }}</span>
                    @foreach ($days as $day => $meetings)
                        <div class="day">
                            @if ($day)
                                <span class="subheading">{{ $day }}</span>
                            @endif
                            @foreach ($meetings as $meeting)
                                @include('meeting', compact('meeting', 'region'))
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            @foreach ($days as $day => $meetings)
                <div class="day">
                    <span class="heading">{{ $day }}</span>
                    @foreach ($meetings as $meeting)
                        @include('meeting', compact('meeting'))
                    @endforeach
                </div>
            @endforeach
        @endif
    </main>
</body>

</html>
