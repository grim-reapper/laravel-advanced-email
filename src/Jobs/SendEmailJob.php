<?php

namespace GrimReapper\AdvancedEmail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Exception; // Import base Exception class

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The mailable instance.
     *
     * @var \Illuminate\Mail\Mailable
     */
    public Mailable $mailable;

    /**
     * The name of the mailer configuration to use.
     *
     * @var string|null
     */
    public ?string $mailerName;

    /**
     * The unique identifier for the email log entry.
     *
     * @var string|null
     */
    public ?string $logUuid;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Mail\Mailable  $mailable
     * @param  string|null  $mailerName
     * @param  string|null  $logUuid The unique ID for the email log entry.
     * @return void
     */
    public function __construct(Mailable $mailable, ?string $mailerName, ?string $logUuid = null)
    {
        $this->mailable = $mailable;
        $this->mailerName = $mailerName;
        $this->logUuid = $logUuid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $providers = Config::get('advanced_email.providers', [Config::get('mail.default', 'smtp')]);
        $strategy = Config::get('advanced_email.failover_strategy', 'sequential');
        $lastException = null;

        // If a specific mailer was requested via mailer(), use only that one.
        if ($this->mailerName) {
            $providers = [$this->mailerName];
        }

        if ($strategy === 'sequential') {
            foreach ($providers as $provider) {
                try {
                    Log::debug("Attempting to send email via provider: {$provider}", ['logUuid' => $this->logUuid]);
                    Mail::mailer($provider)->send($this->mailable);
                    Log::info("Email sent successfully via provider: {$provider}", ['logUuid' => $this->logUuid]);

                    $lastException = null; // Clear exception on success
                    break; // Exit loop on successful send
                } catch (Exception $e) {
                    $lastException = $e;
                    Log::warning("Failed to send email via provider: {$provider}. Error: {$e->getMessage()}", [
                        'logUuid' => $this->logUuid,
                        'exception' => $e
                    ]);
                    // Continue to the next provider
                }
            }
        } else {
            // Handle other strategies like 'random' in the future if needed
            Log::error("Unsupported failover strategy: {$strategy}", ['logUuid' => $this->logUuid]);
            // Fallback to default behavior or throw an error
            try {
                 Mail::mailer($this->mailerName ?? Config::get('mail.default', 'smtp'))->send($this->mailable);
            } catch (Exception $e) {
                 $lastException = $e;
                 Log::error("Failed to send email using default/specified mailer after unsupported strategy.", [
                     'logUuid' => $this->logUuid,
                     'exception' => $e
                 ]);
            }
        }

        // If all providers failed, throw the last exception
        if ($lastException !== null) {
            Log::error("Failed to send email after trying all providers.", [
                'logUuid' => $this->logUuid,
                'exception' => $lastException
            ]);
            // Optionally, dispatch a specific failed event here
            // event(new EmailFailed($this->mailable, $lastException, $this->logUuid));
            throw $lastException; // Re-throw the last exception
        }
    }
}