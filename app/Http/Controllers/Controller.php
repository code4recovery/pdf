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

    public function home()
    {

        // default input
        $json = request(
            'json',
            'https://demo.code4recovery.org/wp-admin/admin-ajax.php?action=meetings'
        );
        $width = request('width', 4.25);
        $height = request('height', 11);
        $numbering = request('numbering', 1);

        // define options
        $fonts = [
            'sans-serif' => 'Sans Serif',
            'serif' => 'Serif',
        ];
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
        ];
        $languages = self::$spec->getLanguages();
        $group_by = [
            'day-region' => 'Day → Region',
            'day' => 'Day',
            'region-day' => 'Region → Day',
        ];
        $types = self::$spec->getTypesByLanguage('en');

        return view('home', compact('fonts', 'modes', 'options', 'languages', 'types', 'group_by', 'json', 'width', 'height', 'numbering'));
    }

    public function pdf()
    {
        // Set to true to preview output as a normal blade template in browser
        $debug = false;

        //parse input
        $json = request('json');
        $width = floatval(request('width', 4.25)) * 72;
        $height = floatval(request('height', 11)) * 72;
        $font = request('font') === 'sans-serif' ? 'Noto Sans JP' : 'Zen Old Mincho';
        $numbering = request('numbering', false);
        if ($numbering) $numbering = intval($numbering);
        $language = request('language', 'en');
        $type = request('type', false);
        $stream = request('mode') === 'stream';
        $options = request('options', []);
        $group_by = request('group_by', 'day-region');
        $types = self::$spec->getTypesByLanguage($language);

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
            'sv' => [
                'days' => ['Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'],
                'noon' => 'Middag',
                'midnight' => 'Midnatt',
                'no_name' => 'Namnlöst möte',
                'meeting_types' => 'Mötestyper',
            ],
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

            //full address alias (GSO)
            if (!empty($meeting->full_address) && empty($meeting->formatted_address)) {
                $meeting->formatted_address = $meeting->full_address;
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

            //no TC meetings
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
        })->map(function ($meeting) use ($strings, $language, $type, $types) {

            //empty meeting name?
            if (empty($meeting->name)) {
                $meeting->name = $strings[$language]['no_name'];
            }

            //make day weekday
            $meeting->day_formatted = $strings[$language]['days'][$meeting->day];

            //make time carbon
            if ($meeting->time === '12:00') {
                $meeting->time_formatted = $strings[$language]['noon'];
            } elseif ($meeting->time === '00:00' || $meeting->time === '23:59') {
                $meeting->time_formatted = $strings[$language]['midnight'];
            } elseif (substr($meeting->time, -3) === ':00') {
                $meeting->time_formatted = date('g a', strtotime($meeting->time));
            } else {
                $meeting->time_formatted = date('g:i a', strtotime($meeting->time));
            }

            //make address
            if (empty($meeting->address)) {
                $meeting->address = empty($meeting->formatted_address) ? '' : explode(',', $meeting->formatted_address)[0];
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
            } elseif (!empty($meeting->region)) {
                $meeting->regions_formatted = $meeting->region;
                if (!empty($meeting->sub_region)) {
                    $meeting->regions_formatted .= ': ' . $meeting->sub_region;
                }
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
        })->sort(function ($a, $b) use ($group_by) {

            //sort meetings by day…
            if ($a->day !== $b->day) {
                return $a->day < $b->day ? -1 : 1;
            }

            if ($group_by === 'day-region' && $a->regions_formatted !== $b->regions_formatted) {
                return strcmp($a->regions_formatted, $b->regions_formatted);
            } elseif ($group_by === 'region-day' && $a->regions_formatted !== $b->regions_formatted) {
                return strcmp($a->regions_formatted, $b->regions_formatted);
            }

            //…then time
            return strcmp($a->time, $b->time);
        });

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

        // Debugging
        if ($debug) {
            return view('pdf', compact('days', 'font', 'numbering', 'group_by', 'types_in_use', 'regions', 'types', 'options', 'meeting_types_heading'));
        }

        //output PDF
        $pdf = PDF::setOption(['isFontSubsettingEnabled' => true, 'isJavascriptEnabled' => false, 'debugLayoutPaddingBox' => false, 'debugLayoutInline' => false, 'debugLayoutBlocks' => false, 'debugLayoutLines' => false])->loadView('pdf', compact('days', 'font', 'numbering', 'group_by', 'types_in_use', 'regions', 'types', 'options', 'meeting_types_heading'))
            ->setPaper([0, 0, $width, $height]);

        return ($stream) ? $pdf->stream() : $pdf->download('directory.pdf');
    }
}
