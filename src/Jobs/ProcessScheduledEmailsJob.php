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
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

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
        try {
            Log::info('Starting ProcessScheduledEmailsJob');

            // Get pending emails that are due to be sent
            $scheduledEmails = ScheduledEmail::where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->take($this->batchSize)
                ->get();

            Log::info('Found ' . $scheduledEmails->count() . ' scheduled emails to process', [
                'batch_size' => $this->batchSize,
                'time' => now(),
                'emails' => $scheduledEmails->map(function($email) {
                    return [
                        'id' => $email->id,
                        'uuid' => $email->uuid,
                        'status' => $email->status,
                        'scheduled_at' => $email->scheduled_at,
                    ];
                })->toArray()
            ]);
            
            // Process failed emails for retry if enabled
            if ($this->processFailedEmails) {
                Log::info('Processing failed emails for retry');
                $this->processFailedEmailsForRetry();
            }

            Log::info('Starting to process scheduled emails');
            foreach ($scheduledEmails as $scheduledEmail) {
                Log::info('Processing email', [
                    'id' => $scheduledEmail->id,
                    'uuid' => $scheduledEmail->uuid,
                    'status' => $scheduledEmail->status,
                    'scheduled_at' => $scheduledEmail->scheduled_at
                ]);

                try {
                    // First check if the email is ready to be sent
                    if (!$scheduledEmail->isReadyToSend()) {
                        Log::info('Email is not ready to send', [
                            'id' => $scheduledEmail->id,
                            'uuid' => $scheduledEmail->uuid,
                            'status' => $scheduledEmail->status
                        ]);
                        continue;
                    }

                    // Mark as processing to prevent duplicate processing
                    if (!$scheduledEmail->update(['status' => 'processing'])) {
                        Log::error('Failed to update email status to processing', [
                            'uuid' => $scheduledEmail->uuid,
                            'id' => $scheduledEmail->id
                        ]);
                        continue;
                    }

                    Log::info('Email marked as processing', [
                        'id' => $scheduledEmail->id,
                        'uuid' => $scheduledEmail->uuid
                    ]);

                    Log::info('Email is ready to send, preparing to send', [
                        'id' => $scheduledEmail->id,
                        'uuid' => $scheduledEmail->uuid
                    ]);

                    // Create email service instance with both MailManager and config
                    $emailService = new EmailService(
                        app(\Illuminate\Mail\MailManager::class),
                        config('advanced_email')
                    );

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
                        // Ensure view_data is an array and handle nested objects
                        $viewData = $scheduledEmail->view_data ?? [];
                        if (is_string($viewData)) {
                            $viewData = json_decode($viewData, true); // true to get arrays instead of objects
                        }
                        $emailService->view($scheduledEmail->view, $viewData);
                    } elseif ($scheduledEmail->html_content) {
                        $emailService->html($scheduledEmail->html_content, $scheduledEmail->placeholders ?? []);
                    }

                    // Set mailer if specified
                    if ($scheduledEmail->mailer) {
                        $emailService->mailer($scheduledEmail->mailer);
                    }

                    Log::info('Sending email', [
                        'id' => $scheduledEmail->id,
                        'uuid' => $scheduledEmail->uuid
                    ]);

                    // Send the email
                    $emailService->send();

                    // Update status and sent_at timestamp
                    if (!$scheduledEmail->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ])) {
                        Log::error('Failed to update email status to sent', [
                            'uuid' => $scheduledEmail->uuid,
                            'id' => $scheduledEmail->id
                        ]);
                    }

                    // For recurring emails, create the next occurrence
                    if ($scheduledEmail->frequency) {
                        $scheduledEmail->createNextOccurrence();
                    }

                    Log::info('Successfully sent scheduled email', [
                        'id' => $scheduledEmail->id,
                        'uuid' => $scheduledEmail->uuid
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to process scheduled email', [
                        'uuid' => $scheduledEmail->uuid,
                        'id' => $scheduledEmail->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Check if we should retry or mark as permanently failed
                    $retryAttempts = $scheduledEmail->retry_attempts ?? 0;
                    
                    if ($retryAttempts < $this->maxRetryAttempts) {
                        // Increment retry attempts and set status back to pending with a delay
                        $retryDelay = $this->calculateRetryDelay($retryAttempts);
                        $nextAttemptAt = now()->addMinutes($retryDelay);
                        
                        if (!$scheduledEmail->update([
                            'status' => 'pending',
                            'scheduled_at' => $nextAttemptAt,
                            'retry_attempts' => $retryAttempts + 1,
                            'error' => $e->getMessage(),
                        ])) {
                            Log::error('Failed to update email for retry', [
                                'uuid' => $scheduledEmail->uuid,
                                'id' => $scheduledEmail->id
                            ]);
                        }
                        
                        Log::warning('Scheduled email failed, will retry', [
                            'uuid' => $scheduledEmail->uuid,
                            'id' => $scheduledEmail->id,
                            'retry_attempt' => $retryAttempts + 1,
                            'next_attempt_at' => $nextAttemptAt,
                            'error' => $e->getMessage(),
                        ]);
                    } else {
                        // Max retries reached, mark as permanently failed
                        if (!$scheduledEmail->update([
                            'status' => 'failed',
                            'error' => $e->getMessage(),
                        ])) {
                            Log::error('Failed to mark email as permanently failed', [
                                'uuid' => $scheduledEmail->uuid,
                                'id' => $scheduledEmail->id
                            ]);
                        }

                        Log::error('Failed to send scheduled email after max retries', [
                            'uuid' => $scheduledEmail->uuid,
                            'id' => $scheduledEmail->id,
                            'retry_attempts' => $retryAttempts,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }
            Log::info('Finished processing scheduled emails');
        } catch (\Exception $e) {
            Log::error('Failed to process scheduled emails job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to ensure the job is marked as failed
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

    /**
     * Convert an array to an object recursively.
     *
     * @param array $array
     * @return object
     */
    protected function arrayToObject(array $array): object
    {
        $obj = new \stdClass();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $obj->$key = $this->arrayToObject($val);
            } else {
                $obj->$key = $val;
            }
        }
        return $obj;
    }
}