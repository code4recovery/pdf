<?php

namespace App\Http\Controllers;

use Code4Recovery\Spec;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Exception;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private static Spec $spec;

    public function __construct()
    {
        self::$spec = new Spec();
    }

    /**
     * Fetch meeting data from a JSON feed or Google Sheets URL.
     *
     * @throws Exception When data cannot be fetched or parsed
     */
    private function fetchMeetingData(string $json): array
    {
        // Is it a Google Sheet?
        $googleSheet = Str::startsWith($json, 'https://docs.google.com/spreadsheets/d/');

        $useJson = $googleSheet
            ? 'https://sheets.googleapis.com/v4/spreadsheets/' . explode('/', $json)[5] . '/values/A1:ZZ?key=' . getenv('GOOGLE_API_KEY')
            : $json;

        // Fetch data
        try {
            $response = Http::withOptions(['verify' => false])->get($useJson);
        } catch (Exception $e) {
            throw new Exception('Could not fetch data. Please check the address. Received the following message: ' . $e->getMessage());
        }

        // Handle fetch error
        if ($response->failed()) {
            if ($googleSheet) {
                throw new Exception('Could not fetch data. Please check that the Google Sheet sharing settings enable anyone with the link to view.');
            }

            $error = 'Could not fetch data. Please check the JSON feed address.';
            switch ($response->status()) {
                case 401:
                    $error = 'Data is protected. If you are using 12 Step Meeting List, consider setting data sharing to open.';
                    break;
                case 403:
                    $error = 'Got a forbidden (403) error. Please check the JSON feed address.';
                    break;
                case 404:
                    $error = 'Received a page not found (404) error. Please check the JSON feed address.';
                    break;
                case 500:
                    $error = 'Received an internal server (500) error. Please check the JSON feed address.';
                    break;
            }
            throw new Exception($error);
        }

        // Parse JSON
        $meetings = $response->json();

        if ($googleSheet) {
            if (empty($meetings['values'])) {
                throw new Exception('Could not get Google Sheet values. Response was ' . substr(trim($response->body()), 0, 100) . '…');
            }

            $headers = array_map(function ($header) {
                return Str::slug($header, '_');
            }, array_shift($meetings['values']));

            $header_count = count($headers);

            $types = self::$spec->getTypesByLanguage('en');
            $type_lookup = array_flip(array_map('strtolower', $types));

            $strings_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $meetings = array_map(function ($row) use ($headers, $header_count, $strings_days, $type_lookup) {
                $row_count = count($row);
                if ($row_count > $header_count) {
                    $row = array_slice($row, 0, $header_count);
                } elseif ($row_count < $header_count) {
                    $row = array_pad($row, $header_count, '');
                }
                $meeting = array_combine($headers, $row);

                if (in_array($meeting['day'], $strings_days)) {
                    $meeting['day'] = array_search($meeting['day'], $strings_days);
                }

                // Format time
                $meeting['time'] = date('H:i', strtotime($meeting['time']));

                // Arrayify types
                $meeting['types'] = array_map('strtolower', array_map('trim', explode(',', $meeting['types'])));

                // Filter out custom types
                $meeting['types'] = array_filter($meeting['types'], function ($type) use ($type_lookup) {
                    return array_key_exists($type, $type_lookup);
                });

                // Convert types to codes
                $meeting['types'] = array_map(function ($type) use ($type_lookup) {
                    return $type_lookup[$type];
                }, $meeting['types']);

                return $meeting;
            }, $meetings['values']);
        } elseif (!is_array($meetings)) {
            throw new Exception('Could not parse JSON data. Response was ' . substr(trim($response->body()), 0, 100) . '…');
        }

        // Where $meetings[n]['day'] is an array, create separate values for each day
        foreach ($meetings as $key => $entry) {
            if (isset($entry['day']) && is_array($entry['day'])) {
                $days = array_filter($entry['day'], 'strlen');
                $first = reset($days);
                foreach ($days as $day) {
                    if ($day == $first) {
                        $meetings[$key]['day'] = $day;
                    } else {
                        $new_meeting = $entry;
                        $new_meeting['day'] = $day;
                        $new_meeting['slug'] .= '-' . $day;
                        $meetings[] = $new_meeting;
                    }
                }
            }
        }

        return $meetings;
    }

    /**
     * Extract unique region names from meeting data.
     *
     * @param array $meetings Raw meeting data
     * @return array Sorted list of unique region names
     */
    private function extractRegions(array $meetings): array
    {
        $regions = [];

        foreach ($meetings as $meeting) {
            $meeting = (object) $meeting;

            // Build region path from regions array (ordered parent to child)
            if (!empty($meeting->regions)) {
                if (is_string($meeting->regions)) {
                    $meeting->regions = array_map('trim', explode('>', $meeting->regions));
                }

                // Add each level of the hierarchy so parent nodes exist in the tree
                $path = [];
                foreach ($meeting->regions as $part) {
                    $path[] = $part;
                    $regions[implode(': ', $path)] = true;
                }
            } elseif (!empty($meeting->city)) {
                $regions_formatted = $meeting->city;
                if (!empty($meeting->state)) {
                    $regions_formatted .= ', ' . $meeting->state;
                }
                $regions[$regions_formatted] = true;
            } else {
                $regions[''] = true;
            }
        }

        $regionList = array_keys($regions);
        sort($regionList, SORT_NATURAL | SORT_FLAG_CASE);

        return $regionList;
    }

    public function home()
    {
        $json = request('json');

        // Screen 1: No JSON URL provided - show simple URL input form
        if (empty($json)) {
            return view('home', ['screen' => 1]);
        }

        // Screen 2: JSON URL provided - fetch data and show full form with regions
        try {
            $meetings = $this->fetchMeetingData($json);
            $availableRegions = $this->extractRegions($meetings);
        } catch (Exception $e) {
            return view('home', [
                'screen' => 1,
                'error' => $e->getMessage(),
            ]);
        }

        // Form defaults
        $width = request('width', 4.25);
        $height = request('height', 11);
        $numbering = request('numbering', 1);

        // Define options for Screen 2
        $fonts = [
            'sans-serif' => 'Sans Serif',
            'serif' => 'Serif',
        ];

        $font_sizes = array_combine(range(8, 12), array_map(fn($i) => "{$i} px", range(8, 12)));

        $modes = [
            'download' => 'Download',
            'stream' => 'Stream',
        ];

        $options = [
            'legend' => [
                'label' => 'Meeting Types Legend',
                'checked' => false,
            ],
            'pagebreaks' => [
                'label' => 'Page Breaks After Groups',
                'checked' => true,
            ],
            'long_address' => [
                'label' => 'Show Long Address on Every Meeting',
                'checked' => false,
            ],
            'time_24hr' => [
                'label' => '24-Hour Time Format',
                'checked' => false,
            ],
        ];

        $languages = self::$spec->getLanguages();

        $group_by = [
            'day-region' => 'Day → Region',
            'day' => 'Day',
            'region-day' => 'Region → Day',
        ];

        $types = self::$spec->getTypesByLanguage('en');

        return view('home', [
            'screen' => 2,
            'json' => $json,
            'availableRegions' => $availableRegions,
            'fonts' => $fonts,
            'font_sizes' => $font_sizes,
            'modes' => $modes,
            'options' => $options,
            'languages' => $languages,
            'types' => $types,
            'group_by' => $group_by,
            'width' => $width,
            'height' => $height,
            'numbering' => $numbering,
        ]);
    }

    public function pdf()
    {
        // Set to true to preview output as a normal blade template in browser
        $debug = false;

        // Set font based on font choice & language (CJK languages need different fonts)
        $baseFont = 'Noto';
        $fontStyle = request('font') === 'sans-serif' ? ' Sans' : ' Serif';
        $fontSuffix = match (request('language')) {
            'ja' => ' JP',
            'th' => ' Thai',
            default => '',
        };
        $font = $baseFont . $fontStyle . $fontSuffix;
        $font_size = request('font_size', 12);  // Default font size is 12 if none is provided

        //parse input
        $json = request('json');
        $width = floatval(request('width', 4.25)) * 72;
        $height = floatval(request('height', 11)) * 72;
        $numbering = request('numbering', false);
        if ($numbering) $numbering = intval($numbering);
        $language = request('language', 'en');
        $type = request('type', false);
        $stream = request('mode') === 'stream';
        $options = request('options', []);
        $group_by = request('group_by', 'day-region');
        $types = self::$spec->getTypesByLanguage($language);

        // Set PDF filename based on choices
        $pdf_name = sprintf(
            '%sx%s_%s-grouped_%s_directory.pdf',
            str_replace('.', '.', request('width')),
            str_replace('.', '.', request('height')),
            $group_by,
            date('Y-m-d')
        );

        //process data
        $strings = [
            'en' => [
                'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'noon' => 'Noon',
                'midnight' => 'Midnight',
                'no_name' => 'Unnamed Meeting',
                'meeting_types' => 'Meeting Types',
            ],
            'es' => [
                'days' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                'noon' => 'Mediodía',
                'midnight' => 'Doce',
                'no_name' => 'Reunión sin nombre',
                'meeting_types' => 'Tipos de reuniones',
            ],
            'fr' => [
                'days' => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
                'noon' => 'Midi',
                'midnight' => 'Minuit',
                'no_name' => 'Réunion sans nom',
                'meeting_types' => 'Types de réunions',
            ],
            'ja' => [
                'days' => ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
                'noon' => 'Midi',
                'midnight' => 'Minuit',
                'no_name' => 'Réunion sans nom',
                'meeting_types' => '会議の種類'
            ],
            'nl' => [
                'days' => ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'],
                'noon' => 'Middag',
                'midnight' => 'Middernacht',
                'no_name' => 'Naamloze vergadering',
                'meeting_types' => 'Soorten vergaderingen',
            ],
            'pt' => [
                'days' => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'],
                'noon' => 'Meio dia',
                'midnight' => 'Meia noite',
                'no_name' => 'Reunião sem nome',
                'meeting_types' => 'Tipos de reunião',
            ],
            'sk' => [
                'days' => ['nedeľa', 'pondelok', 'utorok', 'streda', 'štvrtok', 'piatok', 'sobota'],
                'noon' => 'poludnie',
                'midnight' => 'Polnoc',
                'no_name' => 'Nemenované stretnutie',
                'meeting_types' => 'Typy stretnutí',
            ],
            'sv' => [
                'days' => ['Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'],
                'noon' => 'Middag',
                'midnight' => 'Midnatt',
                'no_name' => 'Namnlöst möte',
                'meeting_types' => 'Mötestyper',
            ],
            'th' => [
                'days' => ['วันอาทิตย์', 'วันจันทร์', 'วันอังคาร', 'วันพุธ', 'วันพฤหัสบดี', 'วันศุกร์', 'วันเสาร์'],
                'noon' => 'เที่ยงวัน',
                'midnight' => 'เที่ยงคืน',
                'no_name' => 'ประชุมที่ไม่มีชื่อ',
                'meeting_types' => 'ประเภทการประชุม',
            ]
        ];

        // Set translated Meeting Types heading
        $meeting_types_heading = $strings[$language]['meeting_types'];

        //is it google sheet?
        $googleSheet = Str::startsWith($json, 'https://docs.google.com/spreadsheets/d/');

        $useJson = $googleSheet
            ? 'https://sheets.googleapis.com/v4/spreadsheets/' . explode('/', $json)[5] . '/values/A1:ZZ?key=' . getenv('GOOGLE_API_KEY')
            : $json;

        //fetch data
        try {
            $response = Http::withOptions(['verify' => false])->get($useJson);
        } catch (Exception $e) {
            $error = 'Could not fetch data. Please check the address. Received the following message: ' . $e->getMessage();
            return back()->with('error', $error)->withInput();
        }

        //handle fetch error
        if ($response->failed()) {
            if ($googleSheet) {
                $error = 'Could not fetch data. Please check that the Google Sheet sharing settings enable anyone with the link to view.';
                //dd($response);
            } else {
                $error = 'Could not fetch data. Please check the JSON feed address.';
                switch ($response->status()) {
                    case 401:
                        $error = 'Data is protected. If you are using 12 Step Meeting List, consider setting data sharing to open.';
                        break;
                    case 403:
                        $error = 'Got a forbidden (403) error. Please check the JSON feed address.';
                        break;
                    case 404:
                        $error = 'Received a page not found (404) error. Please check the JSON feed address.';
                        break;
                    case 500:
                        $error = 'Received an internal server (500) error. Please check the JSON feed address.';
                        break;
                }
            }
            return back()->with('error', $error)->withInput();
        }

        //parse JSON
        $meetings = $response->json();

        if ($googleSheet) {
            if (empty($meetings['values'])) {
                return back()->with('error', 'Could not get Google Sheet values. Response was ' . substr(trim($response->body()), 0, 100) . '…')->withInput();
            }

            $headers = array_map(function ($header) {
                return Str::slug($header, '_');
            }, array_shift($meetings['values']));

            $header_count = count($headers);

            $type_lookup = array_flip(array_map('strtolower', $types));

            $meetings = array_map(function ($row) use ($headers, $header_count, $strings, $type_lookup) {
                $row_count = count($row);
                if ($row_count > $header_count) {
                    $row = array_slice($row, 0, $header_count);
                } elseif ($row_count < $header_count) {
                    $row = array_pad($row, $header_count, '');
                }
                $meeting = array_combine($headers, $row);

                if (in_array($meeting['day'], $strings['en']['days'])) {
                    $meeting['day'] = array_search($meeting['day'], $strings['en']['days']);
                }

                //format time
                $meeting['time'] = date('H:i', strtotime($meeting['time']));

                //arrayify types
                $meeting['types'] = array_map('strtolower', array_map('trim', explode(',', $meeting['types'])));

                //filter out custom types
                $meeting['types'] = array_filter($meeting['types'], function ($type) use ($type_lookup) {
                    return array_key_exists($type, $type_lookup);
                });

                //convert types to codes
                $meeting['types'] = array_map(function ($type) use ($type_lookup) {
                    return $type_lookup[$type];
                }, $meeting['types']);

                return $meeting;
            }, $meetings['values']);
        } elseif (!is_array($meetings)) {
            return back()->with('error', 'Could not parse JSON data. Response was ' . substr(trim($response->body()), 0, 100) . '…')->withInput();
        }

        // Where $meetings[n]['day'] is an array, create separate values for each day
        foreach ($meetings as $key => $entry) {
            if (isset($entry['day']) && is_array($entry['day'])) {
                // Remove non zero empty values (NULL, FALSE & '')
                $days = array_filter($entry['day'], 'strlen');
                $first = reset($days);
                foreach ($days as $day) {
                    if ($day == $first) {
                        $meetings[$key]['day'] = $day;
                    } else {
                        $new_meeting = $entry;
                        $new_meeting['day'] = $day;
                        $new_meeting['slug'] .= '-' . $day;
                        $meetings[] = $new_meeting;
                    }
                }
            }
        }

        //make a laravel collection, sort, & sanitize
        $meetings = collect($meetings)->map(function ($meeting) {
            //convert to object
            $meeting = (object) $meeting;

            //make sure types is an array
            if (!isset($meeting->types) || !is_array($meeting->types)) {
                $meeting->types = [];
            }

            if (empty($meeting->formatted_address)) {
                if (!empty($meeting->full_address)) {
                    //full address alias (GSO)
                    $meeting->formatted_address = $meeting->full_address;
                } else {
                    //try to construct formatted address
                    $meeting->formatted_address = '';
                    if (!empty($meeting->address)) {
                        $meeting->formatted_address .= $meeting->address;
                    }
                    if (!empty($meeting->city)) {
                        $meeting->formatted_address .= ', ' . $meeting->city;
                    }
                    if (!empty($meeting->state)) {
                        $meeting->formatted_address .= ', ' . $meeting->state;
                    }
                    if (!empty($meeting->postal_code)) {
                        $meeting->formatted_address .= ' ' . $meeting->postal_code;
                    }
                    if (!empty($meeting->country)) {
                        $meeting->formatted_address .= ', ' . $meeting->country;
                    }
                }
            }

            return $meeting;
        })->filter(function ($meeting) use ($strings, $language, $type) {
            //validate day
            if (!isset($meeting->day) || !array_key_exists($language, $strings) || !array_key_exists($meeting->day, $strings[$language]['days'])) {
                return false;
            }

            //validate time
            if (empty($meeting->time) || strlen($meeting->time) !== 5) {
                return false;
            }

            //No Temporarily Closed meetings
            if (in_array('TC', $meeting->types)) {
                return false;
            }

            //filter
            if ($type && !in_array($type, $meeting->types)) {
                return false;
            }

            //validate address
            if (!empty($meeting->coordinates)) {
                if (substr_count($meeting->coordinates, ',') > 1) {
                    return false;
                }
            } elseif (!empty($meeting->approximate)) {
                if ($meeting->approximate === 'yes') {
                    return false;
                }
            } elseif (empty($meeting->address) && (empty($meeting->formatted_address) || substr_count($meeting->formatted_address, ', ') !== 3)) {
                return false;
            }

            return true;
        })->map(function ($meeting) use ($strings, $language, $type, $types, $options) {

            //empty meeting name?
            if (empty($meeting->name)) {
                $meeting->name = $strings[$language]['no_name'];
            }

            //make day weekday
            $meeting->day_formatted = $strings[$language]['days'][$meeting->day];

            //format time (24-hour or 12-hour)
            $use24hr = in_array('time_24hr', $options);

            if ($meeting->time === '12:00') {
                $meeting->time_formatted = $use24hr ? '12:00' : $strings[$language]['noon'];
            } elseif ($meeting->time === '00:00' || $meeting->time === '23:59') {
                $meeting->time_formatted = $use24hr ? '00:00' : $strings[$language]['midnight'];
            } elseif (substr($meeting->time, -3) === ':00') {
                $meeting->time_formatted = $use24hr
                    ? date('H:i', strtotime($meeting->time))
                    : date('g a', strtotime($meeting->time));
            } else {
                $meeting->time_formatted = $use24hr
                    ? date('H:i', strtotime($meeting->time))
                    : date('g:i a', strtotime($meeting->time));
            }

            //make address
            if (in_array('long_address', $options)) {
                $meeting->address = $meeting->formatted_address;
            } elseif (empty($meeting->address)) {
                $meeting->address = explode(',', $meeting->formatted_address)[0];
            }

            if (
                empty($meeting->location) ||
                $meeting->location === $meeting->address ||
                (!empty($meeting->formatted_address) && $meeting->location === $meeting->formatted_address)
            ) {
                $meeting->location = null;
            }

            //region(s)
            if (!empty($meeting->regions)) {
                if (is_string($meeting->regions)) {
                    $meeting->regions = array_map('trim', explode('>', $meeting->regions));
                }
                $meeting->regions_formatted = implode(': ', $meeting->regions);
            } elseif (!empty($meeting->city)) {
                $meeting->regions_formatted = $meeting->city;
                if (!empty($meeting->state)) {
                    $meeting->regions_formatted .= ', ' . $meeting->state;
                }
            } else {
                $meeting->regions_formatted = '';
            }

            //sort types for readability
            $meeting->types = array_filter(array_map(function ($type) {
                if ($type === '12x12') return $type;
                return strtoupper($type);
            }, $meeting->types), function ($type) use ($types) {
                return array_key_exists($type, $types);
            });

            if ($type) {
                $meeting->types = array_filter($meeting->types, function ($thistype) use ($type) {
                    return $thistype !== $type;
                });
            }

            sort($meeting->types);

            return $meeting;
        });

        // Filter by selected regions (if provided)
        // Selecting a parent region includes all sub-regions
        $selectedRegions = request('regions', []);
        if (!empty($selectedRegions)) {
            $meetings = $meetings->filter(function ($meeting) use ($selectedRegions) {
                foreach ($selectedRegions as $selected) {
                    // Exact match or is a sub-region (starts with selected + ': ')
                    if ($meeting->regions_formatted === $selected ||
                        str_starts_with($meeting->regions_formatted, $selected . ': ')) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($group_by === 'region-day') {
            $meetings = $meetings->sortBy('time')
                ->sortBy('day')
                ->sortBy('regions_formatted', SORT_NATURAL | SORT_FLAG_CASE);
        } elseif ($group_by === 'day-region') {
            $meetings = $meetings->sortBy('time')
                ->sortBy('regions_formatted', SORT_NATURAL | SORT_FLAG_CASE)
                ->sortBy('day');
        } else {
            $meetings = $meetings->sortBy('time')
                ->sortBy('day');
        }

        $types_in_use = array_unique($meetings->pluck('types')->reduce(function ($carry, $item) {
            return is_array($item) ? array_merge($carry, $item) : $carry;
        }, []));

        sort($types_in_use);

        $regions = [];

        $days = $meetings->groupBy('day_formatted');

        if ($group_by === 'day-region') {
            $days = $days->transform(function ($meetings) {
                return $meetings->groupBy('regions_formatted');
            });
        } elseif ($group_by === 'region-day') {
            $regions = $meetings->groupBy('regions_formatted')->transform(function ($meetings) {
                return $meetings->groupBy('day_formatted');
            });
        } else {
            $days = $meetings->groupBy('day_formatted');
        }

        // Set variables for view
        $viewData = compact('language', 'days', 'font', 'font_size', 'numbering', 'group_by', 'types_in_use', 'regions', 'types', 'options', 'meeting_types_heading');

        // Debugging
        if ($debug) {
            return view('pdf', $viewData);
        }

        //output PDF
        $pdf = PDF::loadView('pdf', $viewData)
            ->setPaper([0, 0, $width, $height]);

        return ($stream) ? $pdf->stream() : $pdf->download($pdf_name);
    }
}
