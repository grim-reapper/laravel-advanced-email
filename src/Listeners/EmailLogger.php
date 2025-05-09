<?php

namespace GrimReapper\AdvancedEmail\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use GrimReapper\AdvancedEmail\Events\EmailSending;
use GrimReapper\AdvancedEmail\Events\EmailSent;
use GrimReapper\AdvancedEmail\Events\EmailFailed;
use Illuminate\Contracts\Queue\ShouldQueue; // Optional: Implement if logging should be queued
use Illuminate\Database\Eloquent\Model;
use GrimReapper\AdvancedEmail\Jobs\SendEmailJob;
use GrimReapper\AdvancedEmail\Models\EmailLog; // Import the EmailLog model
use Illuminate\Support\Str;

class EmailLogger // Optional: implements ShouldQueue
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @return void
     */
    public function subscribe($events): void
    {
        $events->listen(
            EmailSending::class,
            [EmailLogger::class, 'handleEmailSending']
        );

        $events->listen(
            EmailSent::class,
            [EmailLogger::class, 'handleEmailSent']
        );

        $events->listen(
            EmailFailed::class,
            [EmailLogger::class, 'handleEmailFailed']
        );
    }

    /**
     * Handle email sending event.
     *
     * @param  EmailSending  $event
     * @return void
     */
    public function handleEmailSending(EmailSending $event): void
    {
        $this->handleEvent($event, 'sending');
    }

    /**
     * Handle email sent event.
     *
     * @param  EmailSent  $event
     * @return void
     */
    public function handleEmailSent(EmailSent $event): void
    {
        $this->handleEvent($event, 'sent');
    }

    /**
     * Handle email failed event.
     *
     * @param  EmailFailed  $event
     * @return void
     */
    public function handleEmailFailed(EmailFailed $event): void
    {
        $this->handleEvent($event, 'failed');
    }

    /**
     * Handle the event.
     *
     * @param  EmailSending|EmailSent|EmailFailed  $event
     * @param  string  $status
     * @return void
     */
    protected function handleEvent($event, string $status): void
    {
        if (!Config::get('advanced_email.logging.enabled', false)) {
            return;
        }

        $driver = Config::get('advanced_email.logging.driver', 'database'); // Default to database
        $logData = $event->logData;

        // Ensure HTML content is included in log data
        if (isset($logData['mailable']) && method_exists($logData['mailable'], 'getHtmlBody')) {
            $logData['html_content'] = $logData['mailable']->getHtmlBody();
        }

        // Attempt to retrieve UUID from the job if it's a queued event
        $logUuid = $logData['uuid'] ?? null;
        // Ensure UUID exists, though it should always be set earlier
        if (!$logUuid) {
            $logUuid = (string) Str::uuid();
            $logData['uuid'] = $logUuid;
            Log::warning('Advanced Email Logging: UUID was missing in event data, generated new one.', ['event' => get_class($event)]);
        }

        // Set status based on the parameter
        $logData['status'] = $status;
        
        // Add error message for failed emails
        if ($status === 'failed' && $event instanceof EmailFailed) {
            $logData['error'] = $event->exception->getMessage();
        }

        match ($driver) {
            'database' => $this->logToDatabase($logData),
            'log' => $this->logToChannel($logData),
            default => null,
        };
    }

    /**
     * Log email data to the configured database table or model.
     *
     * @param array $logData
     * @return void
     */
    protected function logToDatabase(array $logData): void
    {
        $modelClass = Config::get('advanced_email.logging.database.model', EmailLog::class);
        $connection = Config::get('advanced_email.logging.database.connection');
        $logUuid = $logData['uuid'] ?? null;

        if (!$logUuid) {
            Log::error('Advanced Email Logging: Cannot log to database without a UUID.', ['logData' => $logData]);
            return;
        }

        if (!$modelClass || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            Log::error('Advanced Email Logging: Invalid or missing database model configured.', ['model' => $modelClass]);
            return;
        }

        try {
            /** @var EmailLog|Model $logEntry */
            $logEntry = $modelClass::on($connection)->updateOrCreate(
                ['uuid' => $logUuid], // Find by UUID
                $logData // Data to insert or update
            );
        } catch (\Throwable $e) {
            Log::error('Advanced Email Logging: Failed to log to database.', [
                'error' => $e->getMessage(),
                'uuid' => $logUuid,
                // 'trace' => $e->getTraceAsString(), // Be cautious logging full trace in production
                'data' => $logData
            ]);
        }
    }

    /**
     * Log email data to the configured log channel.
     *
     * @param array $logData
     * @return void
     */
    protected function logToChannel(array $logData): void
    {
        $channel = Config::get('advanced_email.logging.log.channel') ?? config('logging.default');
        $level = ($logData['status'] === 'failed') ? 'error' : 'info';

        Log::channel($channel)->{$level}('Advanced Email Event:', $logData);
    }
}