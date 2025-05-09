<?php

namespace GrimReapper\AdvancedEmail\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use GrimReapper\AdvancedEmail\Models\EmailLog;
use GrimReapper\AdvancedEmail\Models\EmailLink;

class EmailReportController extends Controller
{
    /**
     * Display the email reporting dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $emailLogModelClass = Config::get('advanced_email.logging.database.model', EmailLog::class);
        $emailLinkModelClass = EmailLink::class; // Assuming EmailLink model is always used

        // Basic Stats
        $totalSent = $emailLogModelClass::where('status', 'sent')->count();
        $totalFailed = $emailLogModelClass::where('status', 'failed')->count();
        $totalOpened = $emailLogModelClass::opened()->count();

        // Calculate Open Rate (avoid division by zero)
        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 2) : 0;

        // Total Clicks (requires joining EmailLink)
        $totalClicks = $emailLinkModelClass::distinct('email_log_id')->count('email_log_id');

        // Calculate Click-Through Rate (CTR) based on unique emails clicked
        $ctr = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 2) : 0;

        // Recent Emails (Paginated)
        $recentEmails = $emailLogModelClass::orderBy('sent_at', 'desc')
            ->paginate(15);

        return view('advanced-email::dashboard.index', compact(
            'totalSent',
            'totalFailed',
            'totalOpened',
            'openRate',
            'totalClicks',
            'ctr',
            'recentEmails'
        ));
    }

    /**
     * Display details for a specific email log.
     *
     * @param string $uuid
     * @return \Illuminate\Contracts\View\View
     */
    public function show(string $uuid)
    {
        $emailLogModelClass = Config::get('advanced_email.logging.database.model', EmailLog::class);
        $log = $emailLogModelClass::where('uuid', $uuid)->with('links')->firstOrFail();

        return view('advanced-email::dashboard.show', compact('log'));
    }
}