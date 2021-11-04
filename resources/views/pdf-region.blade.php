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
            font-size: 11pt;
        }

        body::before {
            counter-increment: page {{ $numbering - 1 }};
        }

        h1 {
            border-bottom: 0.5px solid black;
            font-size: 13pt;
            margin: 0 0 10px;
            padding-bottom: 4px;
        }

        h3 {
            font-size: 9pt;
            font-weight: normal;
            margin: 5px 0 7px;
            page-break-after: avoid;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .day {
            page-break-after: always;
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

        .meeting .meeting-time,
        .meeting .meeting-types {
            position: absolute;
            top: 0;
            width: 65px;
            font-size: 9pt;
            padding-top: 2.5px;
        }

        .meeting .meeting-time {
            left: 0;
        }

        .meeting .meeting-types {
            right: 0;
            text-align: right;
        }

        .meeting .meeting-name {
            font-weight: bold;
        }

        footer {
            border-top: 0.5px solid black;
            bottom: 18px;
            left: 0;
            padding-top: 4px;
            position: fixed;
            right: 0;
        }

        footer .page-number {
            font-size: 11pt;
            text-align: center;
        }

        footer .page-number::before {
            content: counter(page);
        }

    </style>
</head>

<body>
    <footer>
        <div class="page-number"></div>
    </footer>
    <main>
        @foreach ($days as $day => $regions)
            <div class="day">
                <h1>{{ $day }}</h1>
                @foreach ($regions as $region => $meetings)
                    <div class="region">
                        @if ($region)
                            <h3>{{ $region }}</h3>
                        @endif
                        @foreach ($meetings as $meeting)
                            <div class="meeting">
                                <div class="meeting-time">
                                    {{ $meeting->time_formatted }}
                                </div>
                                <div>
                                    <div class="meeting-name">
                                        {{ $meeting->name }}
                                    </div>
                                    <div>
                                        {{ $meeting->location }}
                                    </div>
                                    <div>
                                        {{ $meeting->address }}
                                    </div>
                                </div>
                                <div class="meeting-types">
                                    {{ implode(', ', $meeting->types) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </main>
</body>

</html>
