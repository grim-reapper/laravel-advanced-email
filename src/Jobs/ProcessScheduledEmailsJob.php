<?php

namespace GrimReapper\AdvancedEmail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use GrimReapper\AdvancedEmail\Models\ScheduledEmail;
use GrimReapper\AdvancedEmail\Services\EmailService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class ProcessScheduledEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of emails to process in a single batch.
     *
     * @var int
     */
    protected int $batchSize;
    
    /**
     * The maximum number of retry attempts for failed emails.
     *
     * @var int
     */
    protected int $maxRetryAttempts;
    
    /**
     * Whether to process failed emails for retry.
     *
     * @var bool
     */
    protected bool $processFailedEmails;

    /**
     * Create a new job instance.
     *
     * @param int $batchSize The number of emails to process in a single batch
     * @param bool $processFailedEmails Whether to process failed emails for retry
     * @return void
     */
    public function __construct(int $batchSize = 50, bool $processFailedEmails = true)
    {
        $this->batchSize = $batchSize;
        $this->processFailedEmails = $processFailedEmails;
        $this->maxRetryAttempts = Config::get('advanced_email.scheduling.retry.max_attempts', 3);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing scheduled emails');

        // Get pending emails that are due to be sent
        $scheduledEmails = ScheduledEmail::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->take($this->batchSize)
            ->get();

        Log::info('Found ' . $scheduledEmails->count() . ' scheduled emails to process');
        
        // Process failed emails for retry if enabled
        if ($this->processFailedEmails) {
            $this->processFailedEmailsForRetry();
        }

        foreach ($scheduledEmails as $scheduledEmail) {
            try {
                // Mark as processing to prevent duplicate processing
                $scheduledEmail->update(['status' => 'processing']);

                // Check if conditions are met
                if (!$scheduledEmail->isReadyToSend()) {
                    // If not ready but still pending, reset status
                    $scheduledEmail->update(['status' => 'pending']);
                    continue;
                }

                // Create email service instance
                $emailService = App::make(EmailService::class);

                // Configure the email
                if ($scheduledEmail->from) {
                    $from = $scheduledEmail->from;
                    $emailService->from($from['address'], $from['name'] ?? null);
                }

                // Set recipients
                foreach ($scheduledEmail->to as $recipient) {
                    $emailService->to($recipient['address'], $recipient['name'] ?? null);
                }

                if ($scheduledEmail->cc) {
                    foreach ($scheduledEmail->cc as $recipient) {
                        $emailService->cc($recipient['address'], $recipient['name'] ?? null);
                    }
                }

                if ($scheduledEmail->bcc) {
                    foreach ($scheduledEmail->bcc as $recipient) {
                        $emailService->bcc($recipient['address'], $recipient['name'] ?? null);
                    }
                }

                // Set subject
                if ($scheduledEmail->subject) {
                    $emailService->subject($scheduledEmail->subject);
                }

                // Set content (template, view, or HTML)
                if ($scheduledEmail->template_name) {
                    $emailService->template($scheduledEmail->template_name);
                } elseif ($scheduledEmail->view) {
                    $emailService->view($scheduledEmail->view, $scheduledEmail->view_data ?? []);
                } elseif ($scheduledEmail->html_content) {
                    $emailService->html($scheduledEmail->html_content, $scheduledEmail->placeholders ?? []);
                }

                // Set mailer if specified
                if ($scheduledEmail->mailer) {
                    $emailService->mailer($scheduledEmail->mailer);
                }

                // Send the email
                $emailService->send();

                // Update status and sent_at timestamp
                $scheduledEmail->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                // For recurring emails, create the next occurrence
                if ($scheduledEmail->frequency) {
                    $scheduledEmail->createNextOccurrence();
                }

                Log::info('Successfully sent scheduled email', ['uuid' => $scheduledEmail->uuid]);
            } catch (\Throwable $e) {
                // Check if we should retry or mark as permanently failed
                $retryAttempts = $scheduledEmail->retry_attempts ?? 0;
                
                if ($retryAttempts < $this->maxRetryAttempts) {
                    // Increment retry attempts and set status back to pending with a delay
                    $retryDelay = $this->calculateRetryDelay($retryAttempts);
                    $nextAttemptAt = now()->addMinutes($retryDelay);
                    
                    $scheduledEmail->update([
                        'status' => 'pending',
                        'scheduled_at' => $nextAttemptAt,
                        'retry_attempts' => $retryAttempts + 1,
                        'error' => $e->getMessage(),
                    ]);
                    
                    Log::warning('Scheduled email failed, will retry', [
                        'uuid' => $scheduledEmail->uuid,
                        'retry_attempt' => $retryAttempts + 1,
                        'next_attempt_at' => $nextAttemptAt,
                        'error' => $e->getMessage(),
                    ]);
                } else {
                    // Max retries reached, mark as permanently failed
                    $scheduledEmail->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);

                    Log::error('Failed to send scheduled email after max retries', [
                        'uuid' => $scheduledEmail->uuid,
                        'retry_attempts' => $retryAttempts,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }
    
    /**
     * Process failed emails that are eligible for retry.
     *
     * @return void
     */
    protected function processFailedEmailsForRetry(): void
    {
        // Get failed emails that have not reached max retry attempts
        $failedEmails = ScheduledEmail::where('status', 'failed')
            ->whereNotNull('error')
            ->where(function ($query) {
                $query->whereNull('retry_attempts')
                    ->orWhere('retry_attempts', '<', $this->maxRetryAttempts);
            })
            ->take($this->batchSize)
            ->get();
            
        if ($failedEmails->count() > 0) {
            Log::info('Found ' . $failedEmails->count() . ' failed emails to retry');
            
            foreach ($failedEmails as $failedEmail) {
                $retryAttempts = $failedEmail->retry_attempts ?? 0;
                $retryDelay = $this->calculateRetryDelay($retryAttempts);
                
                // Reset to pending with a delay before next attempt
                $failedEmail->update([
                    'status' => 'pending',
                    'scheduled_at' => now()->addMinutes($retryDelay),
                    'retry_attempts' => $retryAttempts + 1,
                ]);
                
                Log::info('Scheduled failed email for retry', [
                    'uuid' => $failedEmail->uuid,
                    'retry_attempt' => $retryAttempts + 1,
                    'next_attempt_at' => now()->addMinutes($retryDelay),
                ]);
            }
        }
    }
    
    /**
     * Calculate the delay before the next retry attempt using exponential backoff.
     *
     * @param int $retryAttempt Current retry attempt number (0-based)
     * @return int Delay in minutes
     */
    protected function calculateRetryDelay(int $retryAttempt): int
    {
        // Get base delay from config or use default of 5 minutes
        $baseDelay = Config::get('advanced_email.scheduling.retry.base_delay', 5);
        
        // Use exponential backoff with jitter
        // Formula: base_delay * (2 ^ attempt) + random jitter
        $delay = $baseDelay * pow(2, $retryAttempt);
        
        // Add some random jitter (0-30% of calculated delay) to prevent thundering herd
        $jitter = mt_rand(0, (int)($delay * 0.3));
        $delay += $jitter;
        
        // Cap at a reasonable maximum (e.g., 2 hours = 120 minutes)
        $maxDelay = Config::get('advanced_email.scheduling.retry.max_delay', 120);
        
        return min($delay, $maxDelay);
    }
}