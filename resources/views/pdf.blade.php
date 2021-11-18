<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style type="text/css">
        @page {
            margin: 18px;
        }

        body {
            color: black;
            font-family: {{ $font }};
            font-size: 12px;
        }

        body::before {
            counter-increment: page {{ $numbering - 1 }};
        }

        h1 {
            border-bottom: 0.5px solid black;
            font-size: 16px;
            margin: 0 0 10px;
            padding-bottom: 4px;
        }

        h3 {
            font-weight: normal;
            font-size: 11px;
            margin: 5px 0 7px;
            page-break-after: avoid;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .day,
        .legend {
            page-break-after: always;
        }

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
            margin-bottom: 10px;
            padding: 0 75px;
            page-break-inside: avoid;
            position: relative;
        }

        .meeting .time,
        .meeting .types {
            position: absolute;
            top: 0;
            width: 65px;
        }

        .meeting .time {
            left: 0;
        }

        .meeting .types {
            right: 0;
            text-align: right;
        }

        .meeting .name {
            font-weight: bold;
        }

        footer {
            bottom: 0;
            height: 20px;
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
    <footer></footer>
    <main>
        @include('legend', compact('types_in_use', 'types'))
        @if ($group_by_region)
            @foreach ($days as $day => $regions)
                <div class="day">
                    <h1>{{ $day }}</h1>
                    @foreach ($regions as $region => $meetings)
                        <div class="region">
                            @if ($region)
                                <h3>{{ $region }}</h3>
                            @endif
                            @foreach ($meetings as $meeting)
                                @include('meeting', compact('meeting'))
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            @foreach ($days as $day => $meetings)
                <div class="day">
                    <h1>{{ $day }}</h1>
                    @foreach ($meetings as $meeting)
                        @include('meeting', compact('meeting'))
                    @endforeach
                </div>
            @endforeach
        @endif
    </main>
</body>

</html>
