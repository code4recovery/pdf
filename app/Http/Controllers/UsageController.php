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
        Inertia::setRootView('dashboard');

        return Inertia::render('Usage', [
            'highlights' => $report->highlights(),
            'monthly' => $report->monthly(12),
            'sources' => $report->sources(),
            'topFeeds' => $report->topFeeds(),
            'topReferrers' => $report->topReferrers(),
            'failures' => $report->failures(),
            'settings' => $report->settings(),
            'heaviest' => $report->heaviest(),
        ]);
    }

    /**
     * Render activity for every month since the service started recording.
     */
    public function months(UsageReport $report): Response
    {
        Inertia::setRootView('dashboard');

        return Inertia::render('UsageMonths', [
            'monthly' => $report->monthly(null),
        ]);
    }

    /**
     * Render the list of every site that has sent traffic to the service.
     */
    public function referrers(UsageReport $report): Response
    {
        Inertia::setRootView('dashboard');

        return Inertia::render('UsageReferrers', [
            'referrers' => $report->referrers(),
        ]);
    }

    /**
     * Render the list of every feed that has used the service.
     */
    public function feeds(UsageReport $report): Response
    {
        Inertia::setRootView('dashboard');

        return Inertia::render('UsageFeeds', [
            'feeds' => $report->feeds(),
        ]);
    }
}
