<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page components
    |--------------------------------------------------------------------------
    |
    | The public form's pages live in resources/js/Pages; the admin usage
    | dashboard's pages live in resources/js/Dashboard/Pages and are built
    | separately (vite.dashboard.config.js). Both folders are listed so
    | `assertInertia` in tests can find every page component.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/Pages'),
            resource_path('js/Dashboard/Pages'),
        ],

        'page_extensions' => ['js', 'jsx'],

    ],

];
