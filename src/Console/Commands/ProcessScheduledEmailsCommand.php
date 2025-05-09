<?php

namespace GrimReapper\AdvancedEmail\Console\Commands;

use Illuminate\Console\Command;
use GrimReapper\AdvancedEmail\Jobs\ProcessScheduledEmailsJob;

class ProcessScheduledEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-scheduled {--batch=50 : Number of emails to process in a single batch} {--retry=1 : Process failed emails for retry (1=yes, 0=no)}'; 

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled emails that are due to be sent';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Get batch size from command option or config
        $batchSize = (int) $this->option('batch') ?: config('advanced_email.scheduling.batch_size', 50);
        
        // Check if scheduling is enabled
        if (!config('advanced_email.scheduling.enabled', true)) {
            $this->info('Email scheduling is disabled in configuration.');
            return 0;
        }
        
        $this->info("Processing scheduled emails (batch size: {$batchSize})...");
        
        // Get retry option
        $processFailedEmails = (bool) $this->option('retry');
        
        // Dispatch the job to process scheduled emails
        // Use the configured queue connection and name if available
        $job = new ProcessScheduledEmailsJob($batchSize, $processFailedEmails);
        
        $queueConnection = config('advanced_email.scheduling.queue.connection');
        $queueName = config('advanced_email.scheduling.queue.name');
        
        $this->info("Processing scheduled emails with batch size: {$batchSize}, retry failed emails: " . ($processFailedEmails ? 'Yes' : 'No'));
        
        if ($queueConnection) {
            $job->onConnection($queueConnection);
        }
        
        if ($queueName) {
            $job->onQueue($queueName);
        }
        
        dispatch($job);
        
        $this->info('Scheduled emails processing job dispatched successfully.');
        
        return 0;
    }
}