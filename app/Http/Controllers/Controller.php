<?php

namespace App\Http\Controllers;

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

    private static $types = [
        '11' => '11th Step Meditation',
        '12x12' => '12 Steps & 12 Traditions',
        'ASL' => 'American Sign Language',
        'ABSI' => 'As Bill Sees It',
        'BA' => 'Babysitting Available',
        'B' => 'Big Book',
        'H' => 'Birthday',
        'BI' => 'Bisexual',
        'BRK' => 'Breakfast',
        'CAN' => 'Candlelight',
        'CF' => 'Child-Friendly',
        'C' => 'Closed',
        'AL-AN' => 'Concurrent with Al-Anon',
        'AL' => 'Concurrent with Alateen',
        'XT' => 'Cross Talk Permitted',
        'DR' => 'Daily Reflections',
        'DB' => 'Digital Basket',
        'D' => 'Discussion',
        'DD' => 'Dual Diagnosis',
        'EN' => 'English',
        'FF' => 'Fragrance Free',
        'FR' => 'French',
        'G' => 'Gay',
        'GR' => 'Grapevine',
        'HE' => 'Hebrew',
        'NDG' => 'Indigenous',
        'ITA' => 'Italian',
        'JA' => 'Japanese',
        'KOR' => 'Korean',
        'L' => 'Lesbian',
        'LGBTQ' => 'LGBTQ',
        'LIT' => 'Literature',
        'LS' => 'Living Sober',
        'TC' => 'Location Temporarily Closed',
        'MED' => 'Meditation',
        'M' => 'Men',
        'N' => 'Native American',
        'BE' => 'Newcomer',
        'O' => 'Open',
        'OUT' => 'Outdoor',
        'POC' => 'People of Color',
        'POL' => 'Polish',
        'POR' => 'Portuguese',
        'P' => 'Professionals',
        'PUN' => 'Punjabi',
        'RUS' => 'Russian',
        'A' => 'Secular',
        'SEN' => 'Seniors',
        'SM' => 'Smoking Permitted',
        'S' => 'Spanish',
        'SP' => 'Speaker',
        'ST' => 'Step Study',
        'TR' => 'Tradition Study',
        'T' => 'Transgender',
        'X' => 'Wheelchair Access',
        'XB' => 'Wheelchair-Accessible Bathroom',
        'W' => 'Women',
        'Y' => 'Young People',
    ];


    public function home()
    {

        //parse input
        $json = request(
            'json',
            'https://demo.code4recovery.org/wp-admin/admin-ajax.php?action=meetings'
            //'https://docs.google.com/spreadsheets/d/12Ga8uwMG4WJ8pZ_SEU7vNETp_aQZ-2yNVsYDFqIwHyE/edit#gid=0'
        );

        $fonts = [
            'sans-serif' => 'Sans Serif',
            'serif' => 'Serif',
        ];
        $modes = [
            'download' => 'Download',
            'stream' => 'Stream',
        ];
        $options = [
            'legend' => 'Meeting Types Legend',
        ];
        $languages = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
        ];
        $group_by = [
            'day-region' => 'Day → Region',
            'day' => 'Day',
            'region-day' => 'Region → Day',
        ];
        $types = self::$types;

        return view('home', compact('fonts', 'modes', 'options', 'languages', 'types', 'group_by', 'json'));
    }

    public function pdf()
    {

        //parse input
        $json = request('json');
        $width = floatval(request('width', 4.25)) * 72;
        $height = floatval(request('height', 11)) * 72;
        $font = request('font') === 'sans-serif' ? 'Helvetica' : 'Georgia';
        $numbering = request('numbering', false);
        if ($numbering) $numbering = intval($numbering);
        $language = request('language', 'en');
        $type = request('type', false);
        $stream = request('mode') === 'stream';
        $options = request('options', []);
        $group_by = request('group_by', 'day-region');
        $types = self::$types;

        //process data
        $strings = [
            'en' => [
                'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'noon' => 'Noon',
                'midnight' => 'Midnight',
                'no_name' => 'Unnamed Meeting',
            ],
            'es' => [
                'days' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                'noon' => 'Mediodía',
                'midnight' => 'Doce',
                'no_name' => 'Reunión sin nombre',
            ],
            'fr' => [
                'days' => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
                'noon' => 'Midi',
                'midnight' => 'Minuit',
                'no_name' => 'Réunion sans nom',
            ],
        ];

        //is it google sheet?
        $googleSheet = Str::startsWith($json, 'https://docs.google.com/spreadsheets/d/');

        $useJson = $googleSheet
            ? 'https://sheets.googleapis.com/v4/spreadsheets/' . explode('/', $json)[5] . '/values/A1:ZZ?key=' . getenv('GOOGLE_API_KEY')
            : $json;

        //fetch data
        try {
            $response = Http::get($useJson);
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

            $type_lookup = array_flip(array_map('strtolower', self::$types));

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
            if (!isset($meeting->day) || !array_key_exists($meeting->day, $strings[$language]['days'])) {
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
            if (!empty($meeting->approximate)) {
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

        //output PDF
        $pdf = PDF::loadView('pdf', compact('days', 'font', 'numbering', 'group_by', 'types_in_use', 'regions', 'types', 'options'))
            ->setPaper([0, 0, $width, $height]);

        return ($stream) ? $pdf->stream() : $pdf->download('directory.pdf');
    }
}
