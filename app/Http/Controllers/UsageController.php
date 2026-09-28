<?php

namespace App\Http\Controllers;

use App\Support\UsageReport;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends BaseController
{
    /**
     * Render the usage-analytics dashboard.
     */
    public function index(UsageReport $report): Response
    {
        return Inertia::render('Usage', [
            'monthly' => $report->monthly(),
            'sources' => $report->sources(),
            'topFeeds' => $report->topFeeds(),
            'topReferrers' => $report->topReferrers(),
            'outcomes' => $report->outcomes(),
            'settings' => $report->settings(),
            'heaviest' => $report->heaviest(),
        ]);
    }

    /**
     * Render the list of every feed that has used the service.
     */
    public function feeds(UsageReport $report): Response
    {
        return Inertia::render('UsageFeeds', [
            'feeds' => $report->feeds(),
        ]);
    }
}
