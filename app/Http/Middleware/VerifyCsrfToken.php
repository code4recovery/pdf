<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * The PDF route is a pure read (it fetches a public feed and returns a document),
     * already answers GET without a token, and is submitted by a plain multipart form.
     *
     * @var array<int, string>
     */
    protected $except = [
        'pdf',
    ];
}
