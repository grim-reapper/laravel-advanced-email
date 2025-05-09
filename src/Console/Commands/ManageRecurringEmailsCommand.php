<?php

namespace GrimReapper\AdvancedEmail\Console\Commands;

use Illuminate\Console\Command;
use GrimReapper\AdvancedEmail\Models\ScheduledEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ManageRecurringEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:manage-recurring 
                            {--cleanup : Clean up expired recurring email campaigns}
                            {--create : Create a new recurring email campaign}
                            {--list : List all recurring email campaigns}
                            {--update=0 : Update a recurring email campaign by ID}
                            {--delete=0 : Delete a recurring email campaign by ID}
                            {--regenerate : Regenerate next occurrences for all active recurring campaigns}
                            {--batch=100 : Process a batch of recurring emails and create next occurrences}'; 

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage recurring email campaigns and perform maintenance tasks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Check if scheduling is enabled
        if (!config('advanced_email.scheduling.enabled', true)) {
            $this->info('Email scheduling is disabled in configuration.');
            return 0;
        }
        
        // Check if recurring emails are enabled
        if (!config('advanced_email.scheduling.recurring.enabled', true)) {
            $this->info('Recurring emails are disabled in configuration.');
            return 0;
        }
        
        if ($this->option('cleanup')) {
            return $this->cleanupExpiredCampaigns();
        }
        
        if ($this->option('create')) {
            return $this->createRecurringCampaign();
        }
        
        if ($this->option('list')) {
            return $this->listRecurringCampaigns();
        }
        
        if ($this->option('update') && $this->option('update') > 0) {
            return $this->updateRecurringCampaign((int)$this->option('update'));
        }
        
        if ($this->option('delete') && $this->option('delete') > 0) {
            return $this->deleteRecurringCampaign((int)$this->option('delete'));
        }
        
        if ($this->option('regenerate')) {
            return $this->regenerateNextOccurrences();
        }
        
        if ($this->option('batch')) {
            return $this->processBatch((int)$this->option('batch'));
        }
        
        // Default action: show stats about recurring campaigns
        return $this->showRecurringCampaignStats();
    }
    
    /**
     * Clean up expired recurring email campaigns.
     *
     * @return int
     */
    protected function cleanupExpiredCampaigns(): int
    {
        $this->info('Cleaning up expired recurring email campaigns...');
        
        // Find expired campaigns (where expires_at is in the past)
        $expiredCount = ScheduledEmail::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'cancelled']);
            
        $this->info("Cancelled {$expiredCount} expired email campaigns.");
        
        // Find and clean up failed campaigns that have reached max retries
        $maxRetries = config('advanced_email.scheduling.retry.max_attempts', 3);
        $failedCount = ScheduledEmail::where('status', 'pending')
            ->where('retry_attempts', '>=', $maxRetries)
            ->update(['status' => 'failed']);
            
        $this->info("Marked {$failedCount} emails as permanently failed after reaching max retry attempts.");
        
        Log::info('Cleaned up expired and failed recurring email campaigns', [
            'expired_count' => $expiredCount,
            'failed_count' => $failedCount,
        ]);
        
        return 0;
    }
    
    /**
     * Show statistics about recurring email campaigns.
     *
     * @return int
     */
    protected function showRecurringCampaignStats(): int
    {
        $this->info('Recurring Email Campaign Statistics');
        $this->info('====================================');
        
        // Count emails by frequency
        $frequencies = ScheduledEmail::whereNotNull('frequency')
            ->selectRaw('frequency, count(*) as count')
            ->groupBy('frequency')
            ->get()
            ->pluck('count', 'frequency')
            ->toArray();
            
        if (empty($frequencies)) {
            $this->info('No recurring email campaigns found.');
        } else {
            $this->info('Campaigns by frequency:');
            foreach ($frequencies as $frequency => $count) {
                $this->line(" - {$frequency}: {$count} campaigns");
            }
        }
        
        // Count by status
        $statuses = ScheduledEmail::whereNotNull('frequency')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
            
        $this->info('\nCampaigns by status:');
        foreach ($statuses as $status => $count) {
            $this->line(" - {$status}: {$count} campaigns");
        }
        
        // Show upcoming scheduled emails
        $upcoming = ScheduledEmail::where('status', 'pending')
            ->whereNotNull('frequency')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();
            
        if ($upcoming->count() > 0) {
            $this->info('\nUpcoming recurring emails:');
            foreach ($upcoming as $email) {
                $this->line(" - {$email->template_name} ({$email->frequency}): scheduled for {$email->scheduled_at}");
            }
        }
        
        return 0;
    }
    
    /**
     * Create a new recurring email campaign.
     *
     * @return int
     */
    protected function createRecurringCampaign(): int
    {
        $this->info('Create a new recurring email campaign');
        $this->info('====================================');
        
        // Collect campaign information
        $subject = $this->ask('Email subject');
        $to = $this->ask('Recipient email address');
        $toName = $this->ask('Recipient name (optional)', '');
        
        // Collect frequency information
        $frequency = $this->choice(
            'Select frequency',
            ['daily', 'weekly', 'monthly', 'custom'],
            'daily'
        );
        
        $frequencyOptions = [];
        
        switch ($frequency) {
            case 'weekly':
                $dayOfWeek = $this->choice(
                    'Select day of week',
                    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    'Monday'
                );
                $dayMap = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 0];
                $frequencyOptions['day_of_week'] = $dayMap[$dayOfWeek];
                break;
                
            case 'monthly':
                $dayOfMonth = $this->ask('Day of month (1-28)', 1);
                $frequencyOptions['day_of_month'] = max(1, min(28, (int)$dayOfMonth));
                break;
                
            case 'custom':
                $interval = $this->ask('Interval (numeric value)', 1);
                $unit = $this->choice(
                    'Select time unit',
                    ['minutes', 'hours', 'days', 'weeks', 'months'],
                    'days'
                );
                $frequencyOptions['interval'] = (int)$interval;
                $frequencyOptions['unit'] = $unit;
                break;
        }
        
        // Ask for max occurrences
        if ($this->confirm('Set maximum number of occurrences?', false)) {
            $maxOccurrences = $this->ask('Maximum occurrences', 10);
            $frequencyOptions['max_occurrences'] = (int)$maxOccurrences;
        }
        
        // Ask for expiration date
        if ($this->confirm('Set expiration date?', false)) {
            $expiresAt = $this->ask('Expiration date (YYYY-MM-DD)', Carbon::now()->addMonths(3)->format('Y-m-d'));
            try {
                $expiresAt = Carbon::createFromFormat('Y-m-d', $expiresAt)->endOfDay();
            } catch (\Exception $e) {
                $this->error('Invalid date format. Using default expiration date (3 months from now).');
                $expiresAt = Carbon::now()->addMonths(3)->endOfDay();
            }
        } else {
            $expiresAt = null;
        }
        
        // Ask for email content
        $contentType = $this->choice(
            'Select content type',
            ['template', 'html', 'view'],
            'template'
        );
        
        $templateName = null;
        $view = null;
        $htmlContent = null;
        
        switch ($contentType) {
            case 'template':
                $templateName = $this->ask('Template name');
                break;
                
            case 'view':
                $view = $this->ask('View name');
                break;
                
            case 'html':
                $htmlContent = $this->ask('HTML content (or path to HTML file)');
                if (file_exists($htmlContent)) {
                    $htmlContent = file_get_contents($htmlContent);
                }
                break;
        }
        
        // Create the scheduled email
        $scheduledEmail = new ScheduledEmail();
        $scheduledEmail->uuid = (string) Str::uuid();
        $scheduledEmail->status = 'pending';
        $scheduledEmail->scheduled_at = Carbon::now()->addMinutes(5); // Start in 5 minutes
        $scheduledEmail->expires_at = $expiresAt;
        $scheduledEmail->frequency = $frequency;
        $scheduledEmail->frequency_options = $frequencyOptions;
        $scheduledEmail->subject = $subject;
        $scheduledEmail->to = [['address' => $to, 'name' => $toName]];
        $scheduledEmail->template_name = $templateName;
        $scheduledEmail->view = $view;
        $scheduledEmail->html_content = $htmlContent;
        $scheduledEmail->save();
        
        $this->info("Recurring email campaign created successfully with ID: {$scheduledEmail->id}");
        $this->info("First occurrence scheduled for: {$scheduledEmail->scheduled_at}");
        
        return 0;
    }
    
    /**
     * Regenerate next occurrences for all active recurring campaigns.
     *
     * @return int
     */
    protected function regenerateNextOccurrences(): int
    {
        $this->info('Regenerating next occurrences for active recurring campaigns...');
        
        // Find all active recurring campaigns that have been sent
        $activeRecurring = ScheduledEmail::where('status', 'sent')
            ->whereNotNull('frequency')
            ->orderBy('sent_at', 'desc')
            ->get();
            
        if ($activeRecurring->isEmpty()) {
            $this->info('No active recurring campaigns found.');
            return 0;
        }
        
        $this->info("Found {$activeRecurring->count()} active recurring campaigns.");
        $count = 0;
        
        $progressBar = $this->output->createProgressBar($activeRecurring->count());
        $progressBar->start();
        
        foreach ($activeRecurring as $email) {
            try {
                $nextOccurrence = $email->createNextOccurrence();
                
                if ($nextOccurrence) {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::error('Error regenerating next occurrence', [
                    'uuid' => $email->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info("Successfully regenerated {$count} next occurrences.");
        
        return 0;
    }
    
    /**
     * Process a batch of recurring emails and create next occurrences.
     *
     * @param int $limit Maximum number of emails to process
     * @return int
     */
    protected function processBatch(int $limit = 100): int
    {
        $this->info("Processing batch of up to {$limit} recurring emails...");
        
        $count = ScheduledEmail::processBatchRecurring($limit);
        
        $this->info("Successfully processed {$count} recurring emails.");
        
        return 0;
    }
    
    /**
     * List all recurring email campaigns.
     *
     * @return int
     */
    protected function listRecurringCampaigns(): int
    {
        $this->info('Recurring Email Campaigns');
        $this->info('========================');
        
        $campaigns = ScheduledEmail::whereNotNull('frequency')
            ->orderBy('id', 'desc')
            ->get();
            
        if ($campaigns->isEmpty()) {
            $this->info('No recurring email campaigns found.');
            return 0;
        }
        
        $headers = ['ID', 'Subject', 'Frequency', 'Status', 'Next Scheduled', 'Expires At'];
        $rows = [];
        
        foreach ($campaigns as $campaign) {
            $rows[] = [
                $campaign->id,
                $campaign->subject,
                $this->formatFrequency($campaign),
                $campaign->status,
                $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d H:i:s') : 'N/A',
                $campaign->expires_at ? $campaign->expires_at->format('Y-m-d') : 'Never',
            ];
        }
        
        $this->table($headers, $rows);
        
        return 0;
    }
    
    /**
     * Format the frequency for display.
     *
     * @param ScheduledEmail $campaign
     * @return string
     */
    protected function formatFrequency(ScheduledEmail $campaign): string
    {
        $frequency = $campaign->frequency;
        $options = $campaign->frequency_options ?? [];
        
        switch ($frequency) {
            case 'daily':
                return 'Daily';
                
            case 'weekly':
                if (isset($options['day_of_week'])) {
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $day = $days[$options['day_of_week'] % 7];
                    return "Weekly on {$day}";
                }
                return 'Weekly';
                
            case 'monthly':
                if (isset($options['day_of_month'])) {
                    return "Monthly on day {$options['day_of_month']}";
                }
                return 'Monthly';
                
            case 'custom':
                if (isset($options['interval']) && isset($options['unit'])) {
                    $interval = $options['interval'];
                    $unit = $options['unit'];
                    return "Every {$interval} {$unit}";
                }
                return 'Custom';
                
            default:
                return $frequency;
        }
    }
    
    /**
     * Update a recurring email campaign.
     *
     * @param int $id
     * @return int
     */
    protected function updateRecurringCampaign(int $id): int
    {
        $campaign = ScheduledEmail::find($id);
        
        if (!$campaign) {
            $this->error("Campaign with ID {$id} not found.");
            return 1;
        }
        
        if (!$campaign->frequency) {
            $this->error("Campaign with ID {$id} is not a recurring campaign.");
            return 1;
        }
        
        $this->info("Updating recurring email campaign ID: {$id}");
        $this->info('====================================');
        
        // Show current values
        $this->info("Current subject: {$campaign->subject}");
        $this->info("Current frequency: {$this->formatFrequency($campaign)}");
        $this->info("Current status: {$campaign->status}");
        
        // Update subject
        if ($this->confirm('Update subject?', false)) {
            $subject = $this->ask('New subject', $campaign->subject);
            $campaign->subject = $subject;
        }
        
        // Update status
        if ($this->confirm('Update status?', false)) {
            $status = $this->choice(
                'New status',
                ['pending', 'paused', 'cancelled'],
                $campaign->status
            );
            $campaign->status = $status;
        }
        
        // Update frequency
        if ($this->confirm('Update frequency?', false)) {
            $frequency = $this->choice(
                'Select frequency',
                ['daily', 'weekly', 'monthly', 'custom'],
                $campaign->frequency
            );
            
            $frequencyOptions = [];
            
            switch ($frequency) {
                case 'weekly':
                    $dayOfWeek = $this->choice(
                        'Select day of week',
                        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                        'Monday'
                    );
                    $dayMap = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 0];
                    $frequencyOptions['day_of_week'] = $dayMap[$dayOfWeek];
                    break;
                    
                case 'monthly':
                    $dayOfMonth = $this->ask('Day of month (1-28)', 1);
                    $frequencyOptions['day_of_month'] = max(1, min(28, (int)$dayOfMonth));
                    break;
                    
                case 'custom':
                    $interval = $this->ask('Interval (numeric value)', 1);
                    $unit = $this->choice(
                        'Select time unit',
                        ['minutes', 'hours', 'days', 'weeks', 'months'],
                        'days'
                    );
                    $frequencyOptions['interval'] = (int)$interval;
                    $frequencyOptions['unit'] = $unit;
                    break;
            }
            
            // Ask for max occurrences
            if ($this->confirm('Set maximum number of occurrences?', false)) {
                $maxOccurrences = $this->ask('Maximum occurrences', 10);
                $frequencyOptions['max_occurrences'] = (int)$maxOccurrences;
            }
            
            $campaign->frequency = $frequency;
            $campaign->frequency_options = $frequencyOptions;
        }
        
        // Update expiration date
        if ($this->confirm('Update expiration date?', false)) {
            $currentExpiry = $campaign->expires_at ? $campaign->expires_at->format('Y-m-d') : null;
            
            if ($this->confirm('Remove expiration date?', false)) {
                $campaign->expires_at = null;
            } else {
                $expiresAt = $this->ask(
                    'New expiration date (YYYY-MM-DD)', 
                    $currentExpiry ?? Carbon::now()->addMonths(3)->format('Y-m-d')
                );
                
                try {
                    $campaign->expires_at = Carbon::createFromFormat('Y-m-d', $expiresAt)->endOfDay();
                } catch (\Exception $e) {
                    $this->error('Invalid date format. Keeping current expiration date.');
                }
            }
        }
        
        // Save changes
        $campaign->save();
        
        $this->info("Campaign updated successfully.");
        return 0;
    }
    
    /**
     * Delete a recurring email campaign.
     *
     * @param int $id
     * @return int
     */
    protected function deleteRecurringCampaign(int $id): int
    {
        $campaign = ScheduledEmail::find($id);
        
        if (!$campaign) {
            $this->error("Campaign with ID {$id} not found.");
            return 1;
        }
        
        if (!$campaign->frequency) {
            $this->error("Campaign with ID {$id} is not a recurring campaign.");
            return 1;
        }
        
        $this->info("Deleting recurring email campaign ID: {$id}");
        $this->info("Subject: {$campaign->subject}");
        $this->info("Frequency: {$this->formatFrequency($campaign)}");
        
        if (!$this->confirm('Are you sure you want to delete this campaign?', false)) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        // Ask if future occurrences should also be deleted
        $deleteFuture = false;
        if ($campaign->status === 'pending') {
            $deleteFuture = $this->confirm('Delete all future occurrences of this campaign?', false);
        }
        
        if ($deleteFuture) {
            // Delete all pending occurrences with the same template name and frequency
            $deleted = ScheduledEmail::where('template_name', $campaign->template_name)
                ->where('frequency', $campaign->frequency)
                ->where('status', 'pending')
                ->delete();
                
            $this->info("Deleted {$deleted} campaign occurrences.");
        } else {
            // Just delete this specific campaign
            $campaign->delete();
            $this->info('Campaign deleted successfully.');
        }
        
        return 0;
    }
}