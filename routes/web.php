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
        return view('home', compact('fonts', 'modes'));
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
    $days = processData($meetings);

    //dd($meetings);

    //output PDF
    $pdf = PDF::loadView('pdf', compact('days', 'font', 'numbering'))->setPaper([0, 0, $width, $height]);
    if ($stream) {
        return $pdf->stream();
    }
    return $pdf->download('directory.pdf');
});

function processData($meetings)
{
    //need for parsing with carbon, using weekday integer doesn't work
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    //make a laravel collection, sort, & sanitize
    $meetings = collect($meetings)->map(function ($meeting, $key) {
        //make sure types is an array
        if (!isset($meeting->types) || !is_array($meeting->types)) {
            $meeting->types = [];
        }
        return $meeting;
    })->filter(function ($meeting, $key) use ($days) {
        //validate day
        if (!isset($meeting->day) || !array_key_exists($meeting->day, $days)) {
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

        //validate address
        if (empty($meeting->address) && (empty($meeting->formatted_address) || substr_count($meeting->formatted_address, ', ') !== 3)) {
            return false;
        }

        return true;
    })->map(function ($meeting, $key) use ($days) {
        //make day weekday
        $meeting->day_formatted = $days[$meeting->day];

        //make time carbon
        if ($meeting->time === '12:00') {
            $meeting->time_formatted = 'Noon';
        } elseif ($meeting->time === '00:00' || $meeting->time === '23:59') {
            $meeting->time_formatted = 'Midnight';
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
        if (empty($meeting->regions)) {
            if (empty($meeting->region)) {
                $meeting->regions_formatted = '';
            } else {
                $meeting->regions_formatted = $meeting->region;
                if (!empty($meeting->sub_region)) {
                    $meeting->regions_formatted .= ': ' . $meeting->sub_region;
                }
            }
        } else {
            $meeting->regions_formatted = implode(': ', $meeting->regions);
        }

        //sort types for readability
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
