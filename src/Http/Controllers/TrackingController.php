<?php

namespace GrimReapper\AdvancedEmail\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use GrimReapper\AdvancedEmail\Models\EmailLog; // Assuming EmailLog exists
use GrimReapper\AdvancedEmail\Models\EmailLink; // Add EmailLink model
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TrackingController extends Controller
{
    /**
     * Handle the tracking of an email open event.
     *
     * @param string $logUuid The UUID of the email log.
     * @return \Illuminate\Http\Response
     */
    public function trackOpen(string $logUuid)
    {
        try {
            // Find the email log by UUID
            $emailLog = EmailLog::where('uuid', $logUuid)->firstOrFail();

            // Update the opened_at timestamp if it's not already set
            if (is_null($emailLog->opened_at)) {
                $emailLog->opened_at = now();
                $emailLog->save();
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log if the email log is not found, but don't expose details
            Log::warning("Email open tracking failed: Log UUID [{$logUuid}] not found.");
            // Return a 404 response to avoid revealing information
            abort(404);
        } catch (\Exception $e) {
            // Log any other errors during tracking
            Log::error("Error tracking email open for UUID [{$logUuid}]: " . $e->getMessage());
            // Return a generic error response or a 404
            abort(500); // Or abort(404) depending on desired behavior
        }

        // Return a 1x1 transparent pixel image
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($pixel, 200)->header('Content-Type', 'image/gif');
    }

    /**
     * Handle the tracking of an email link click event.
     *
     * @param string $uuid The UUID of the email log.
     * @param string $linkToken The unique token identifying the link.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function trackLinkClick(string $uuid, string $linkToken)
    {
        try {
            // Find the email link by its unique tracking token
            $emailLink = EmailLink::where('uuid', $linkToken)->firstOrFail();

            // Increment click count and update last clicked timestamp
            $emailLink->increment('click_count');
            $emailLink->clicked_at = Carbon::now();
            $emailLink->save();

            // Validate URL scheme before redirecting
            $scheme = parse_url($emailLink->original_url, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme ?? ''), ['http', 'https'])) {
                Log::warning("Attempted redirect to URL with invalid scheme: {$emailLink->original_url}");
                abort(400, 'Invalid redirect URL.');
            }

            // Redirect the user to the original URL
            return redirect()->away($emailLink->original_url);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("Email link click tracking failed: Link token [{$linkToken}] not found.");
            // Return a 404 response if the link is not found
            abort(404);
        } catch (\Exception $e) {
            Log::error("Error tracking email link click for token [{$linkToken}]: " . $e->getMessage());
            // Return a generic error response
            abort(500);
        }
    }
}