<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code for Recovery Lines
    |--------------------------------------------------------------------------
    */

    'pdf' => [
        'header' => ['title' => 'PDF Generator'],
        'home' => [
            'title' => 'PDF Generator',
            'subtitle' => 'This service creates inside pages for a printed meeting schedule from a Meeting ' .
                'Guide JSON feed or Google Sheet.',
            'label' => [
                'url' => 'Feed or Sheet URL',
                'width' => 'Width',
                'height' => 'Height',
                'start' => 'Start #',
                'type' => 'Type',
                'any_type' => 'Any Type',
                'language' => 'Language',
                'font' => 'Font',
                'sans_serif' => 'Sans Serif',
                'serif' => 'Serif',
                'group_by' => 'Group By',
                'day_region' => 'Day → Region',
                'day' => 'Day',
                'region_day' => 'Region → Day',
                'mode' => 'Mode',
                'options' => 'Options',
                'en' => 'English',
                'es' => 'Español',
                'fr' => 'Français',
                'download' => 'Download',
                'stream' => 'Stream',
                'meeting_types_legend' => 'Meeting Types Legend',
                'omit_page_numbering' => 'Omit Page Numbering',
                'generate' => 'Generate',
                // This is not at all elegant, but it works; WARNING: this is not escaped when put in the template.
                'more_info' =>
                    'More information is available on the ' .
                    '<a href="https://github.com/code4recovery/pdf" target="_blank"> project page on Github</a>. To ' .
                    'get help, please <a href="https://github.com/code4recovery/pdf/issues" target="_blank">' .
                    'file an issue</a>.',
            ],
        ],
    ],
];
