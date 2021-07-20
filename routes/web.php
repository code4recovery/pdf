<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::get('/', function (Request $request) {

    //parse input
    $json = request('json');
    $width = floatval(request('width', 4.25)) * 72;
    $height = floatval(request('height', 11)) * 72;
    $font = request('font') === 'sans-serif' ? 'Helvetica' : 'Georgia';
    $numbering = intval(request('numbering', 1));
    $language = request('language', 'en');
    $type = request('type', false);
    $stream = request('mode') === 'stream';

    //show home page if JSON isn't set
    if (!$json) {
        $fonts = [
            'serif' => 'Serif',
            'sans-serif' => 'Sans Serif',
        ];
        $modes = [
            'stream' => 'Stream (in-browser)',
            'download' => 'Download',
        ];
        $languages = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
        ];
        $types = [
            '11' => '11th Step Meditation',
            '12x12' => '12 Steps & 12 Traditions',
            'ABSI' => 'As Bill Sees It',
            'BA' => 'Babysitting Available',
            'B' => 'Big Book',
            'H' => 'Birthday',
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
            'LIT' => 'Literature',
            'LS' => 'Living Sober',
            'LGBTQ' => 'LGBTQ',
            'MED' => 'Meditation',
            'M' => 'Men',
            'N' => 'Native American',
            'BE' => 'Newcomer',
            //'NS'     => 'Non-Smoking', //here for the count
            //'ONL'    => 'Online Meeting',
            'O' => 'Open',
            'OUT' => 'Outdoor Meeting',
            'POC' => 'People of Color',
            'POL' => 'Polish',
            'POR' => 'Portuguese',
            'P' => 'Professionals',
            'PUN' => 'Punjabi',
            'RUS' => 'Russian',
            'A' => 'Secular',
            'SEN' => 'Seniors',
            'ASL' => 'Sign Language',
            'SM' => 'Smoking Permitted',
            'S' => 'Spanish',
            'SP' => 'Speaker',
            'ST' => 'Step Study',
            'TR' => 'Tradition Study',
            //'TC'    => 'Temporary Closure', //todo update to store codes
            'T' => 'Transgender',
            'X' => 'Wheelchair Access',
            'XB' => 'Wheelchair-Accessible Bathroom',
            'W' => 'Women',
            'Y' => 'Young People',
        ];
        return view('home', compact('fonts', 'modes', 'languages', 'types'));
    }

    //fetch data
    $data = @file_get_contents($json);
    if (!$data) {
        return back()->with('error', 'Could not fetch data. Please check the address.')->withInput();
    }

    //parse JSON
    $meetings = @json_decode($data);
    if (!is_array($meetings)) {
        return back()->with('error', 'Could not parse JSON data. Response was ' . substr(trim($data), 0, 100) . '…')->withInput();
    }

    //process data
    $days = processData($meetings, $language, $type);

    //dd($meetings);

    //output PDF
    $pdf = PDF::loadView('pdf', compact('days', 'font', 'numbering'))->setPaper([0, 0, $width, $height]);
    if ($stream) {
        return $pdf->stream();
    }
    return $pdf->download('directory.pdf');
});

function processData($meetings, $language, $type)
{
    //need for parsing with carbon, using weekday integer doesn't work
    $strings = [
        'en' => [
            'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'noon' => 'Noon',
            'midnight' => 'Midnight',
        ],
        'es' => [
            'days' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'noon' => 'Mediodía',
            'midnight' => 'Doce',
        ],
        'fr' => [
            'days' => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
            'noon' => 'Midi',
            'midnight' => 'Minuit',
        ],
    ];

    //make a laravel collection, sort, & sanitize
    $meetings = collect($meetings)->map(function ($meeting, $key) {
        //make sure types is an array
        if (!isset($meeting->types) || !is_array($meeting->types)) {
            $meeting->types = [];
        }
        return $meeting;
    })->filter(function ($meeting, $key) use ($strings, $language, $type) {
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
        if (empty($meeting->address) && (empty($meeting->formatted_address) || substr_count($meeting->formatted_address, ', ') !== 3)) {
            return false;
        }

        return true;
    })->map(function ($meeting, $key) use ($strings, $language, $type) {
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
            list($address, $city, $state_zip, $country) = explode(', ', $meeting->formatted_address);
            $meeting->address = $address;
        }

        if ($meeting->location === $meeting->address || (!empty($meeting->formatted_address) && $meeting->location === $meeting->formatted_address)) {
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
        $meeting->types = array_map('strtoupper', $meeting->types);
        if ($type) {
            $meeting->types = array_filter($meeting->types, function ($thistype) use ($type) {
                return $thistype !== $type;
            });
        }

        sort($meeting->types);

        return $meeting;
    })->sort(function ($a, $b) {

        //sort meetings by day…
        if ($a->day !== $b->day) {
            return $a->day < $b->day ? -1 : 1;
        }

        if ($a->regions_formatted !== $b->regions_formatted) {
            return strcmp($a->regions_formatted, $b->regions_formatted);
        }

        //…then time
        return strcmp($a->time, $b->time);
    })->groupBy('day_formatted')->transform(function ($meetings) {
        return $meetings->groupBy('regions_formatted');
    });

    //dd($meetings);

    return $meetings;
}
