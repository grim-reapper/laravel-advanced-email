<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduledEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'status',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'frequency',
        'frequency_options',
        'conditions',
        'mailer',
        'from',
        'to',
        'cc',
        'bcc',
        'subject',
        'template_name',
        'view',
        'html_content',
        'view_data',
        'placeholders',
        'attachments',
        'error',
        'retry_attempts',
        'parent_id',
        'occurrence_number',
    ];

    protected $casts = [
        'from' => 'array',
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'view_data' => 'array',
        'placeholders' => 'array',
        'attachments' => 'array',
        'conditions' => 'array',
        'frequency_options' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'occurrence_number' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        // Use the table name defined in the config, or default to 'scheduled_emails'
        return config('advanced_email.database.tables.scheduled_emails', 'scheduled_emails');
    }

    /**
     * Get the database connection for the model.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        // Use the connection name defined in the config, or the default connection
        return config('advanced_email.database.connection');
    }
    
    /**
     * Get the parent email for recurring emails.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(ScheduledEmail::class, 'parent_id');
    }
    
    /**
     * Get the child emails for recurring emails.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(ScheduledEmail::class, 'parent_id');
    }

    /**
     * Check if the scheduled email is ready to be sent.
     *
     * @return bool
     */
    public function isReadyToSend(): bool
    {
        // Check if the email is pending and scheduled time has passed
        if ($this->status !== 'pending') {
            return false;
        }

        if ($this->scheduled_at->isFuture()) {
            return false;
        }

        // Check if the email has expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->update(['status' => 'cancelled']);
            return false;
        }

        // Check conditions if any
        if (!$this->evaluateConditions()) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate conditions for conditional sending.
     *
     * @return bool
     */
    protected function evaluateConditions(): bool
    {
        // If no conditions are set, or conditions are disabled in config, return true
        if (empty($this->conditions) || !config('advanced_email.scheduling.triggers.enabled', true)) {
            return true;
        }

        // Process each condition
        foreach ($this->conditions as $condition) {
            // Skip invalid conditions
            if (!isset($condition['type'])) {
                continue;
            }

            // Handle different condition types
            switch ($condition['type']) {
                case 'event':
                    // Check if a specific event has occurred
                    if (!$this->checkEventCondition($condition)) {
                        return false;
                    }
                    break;

                case 'callback':
                    // Execute a callback function (if available)
                    if (isset($condition['callback']) && is_callable($condition['callback'])) {
                        if (!call_user_func($condition['callback'], $this)) {
                            return false;
                        }
                    }
                    break;

                case 'database':
                    // Check a database condition
                    if (!$this->checkDatabaseCondition($condition)) {
                        return false;
                    }
                    break;

                case 'time':
                    // Check time-based conditions
                    if (!$this->checkTimeCondition($condition)) {
                        return false;
                    }
                    break;
                    
                // Legacy condition types
                case 'date_range':
                    // Check if current date is within range
                    if (isset($condition['value']['start']) && isset($condition['value']['end'])) {
                        $start = new \DateTime($condition['value']['start']);
                        $end = new \DateTime($condition['value']['end']);
                        $now = new \DateTime();
                        if ($now < $start || $now > $end) {
                            return false;
                        }
                    }
                    break;
                    
                // Add more condition types as needed
                // case 'event_triggered':
                // case 'user_action':
                // etc.
            }
        }

        return true;
    }

    /**
     * Check if an event-based condition is met.
     *
     * @param array $condition
     * @return bool
     */
    protected function checkEventCondition(array $condition): bool
    {
        // Implementation would depend on your event tracking system
        // This is a placeholder implementation
        if (!isset($condition['event_name'])) {
            return false;
        }

        // Example: Check if an event has been recorded for a specific entity
        if (isset($condition['entity_type']) && isset($condition['entity_id'])) {
            // Query your events table or system to check if the event occurred
            // Return true if the event exists, false otherwise
        }

        // Default to false if we can't verify the event
        return false;
    }

    /**
     * Check if a database condition is met.
     *
     * @param array $condition
     * @return bool
     */
    protected function checkDatabaseCondition(array $condition): bool
    {
        // Ensure we have the necessary condition parameters
        if (!isset($condition['table']) || !isset($condition['where'])) {
            return false;
        }

        try {
            // Build a query based on the condition
            $query = \DB::table($condition['table']);

            // Apply where clauses
            foreach ($condition['where'] as $column => $value) {
                if (is_array($value) && count($value) >= 2) {
                    // If value is an array, treat it as [operator, value]
                    $query->where($column, $value[0], $value[1]);
                } else {
                    // Simple equality check
                    $query->where($column, $value);
                }
            }

            // Check if any records match the condition
            return $query->exists();
        } catch (\Exception $e) {
            // Log the error and return false
            \Log::error('Error evaluating database condition: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a time-based condition is met.
     *
     * @param array $condition
     * @return bool
     */
    protected function checkTimeCondition(array $condition): bool
    {
        $now = now();

        // Check day of week
        if (isset($condition['days_of_week'])) {
            $currentDayOfWeek = $now->dayOfWeek;
            if (!in_array($currentDayOfWeek, (array)$condition['days_of_week'])) {
                return false;
            }
        }

        // Check time of day
        if (isset($condition['time_range'])) {
            $range = $condition['time_range'];
            $currentTime = $now->format('H:i:s');

            if (isset($range['start']) && isset($range['end'])) {
                if ($currentTime < $range['start'] || $currentTime > $range['end']) {
                    return false;
                }
            }
        }

        // Check date range
        if (isset($condition['date_range'])) {
            $range = $condition['date_range'];
            $currentDate = $now->format('Y-m-d');

            if (isset($range['start']) && $currentDate < $range['start']) {
                return false;
            }

            if (isset($range['end']) && $currentDate > $range['end']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate the next occurrence for recurring emails.
     *
     * @return \DateTime|null
     */
    public function calculateNextOccurrence(): ?\DateTime
    {
        if (empty($this->frequency)) {
            return null; // Not a recurring email
        }

        $options = $this->frequency_options ?? [];
        $lastSent = $this->sent_at ?? $this->scheduled_at;
        $next = null;

        switch ($this->frequency) {
            case 'daily':
                $next = $lastSent->copy()->addDay();
                break;
                
            case 'weekly':
                $next = $lastSent->copy()->addWeek();
                // Adjust for specific day of week if specified
                if (isset($options['day_of_week'])) {
                    $next = $next->next((int)$options['day_of_week']);
                }
                break;
                
            case 'monthly':
                $next = $lastSent->copy()->addMonth();
                // Adjust for specific day of month if specified
                if (isset($options['day_of_month'])) {
                    $day = min((int)$options['day_of_month'], $next->daysInMonth);
                    $next->day = $day;
                }
                break;
                
            case 'custom':
                // Handle custom recurrence rules
                if (isset($options['interval']) && isset($options['unit'])) {
                    $interval = (int)$options['interval'];
                    $unit = $options['unit'];
                    
                    switch ($unit) {
                        case 'minutes':
                            $next = $lastSent->copy()->addMinutes($interval);
                            break;
                        case 'hours':
                            $next = $lastSent->copy()->addHours($interval);
                            break;
                        case 'days':
                            $next = $lastSent->copy()->addDays($interval);
                            break;
                        case 'weeks':
                            $next = $lastSent->copy()->addWeeks($interval);
                            break;
                        case 'months':
                            $next = $lastSent->copy()->addMonths($interval);
                            break;
                    }
                }
                break;
        }

        return $next;
    }
    
    /**
     * Process batch of recurring emails and create next occurrences.
     *
     * @param int $limit Maximum number of emails to process in one batch
     * @return int Number of new occurrences created
     */
    public static function processBatchRecurring(int $limit = 100): int
    {
        // Check if recurring emails are enabled in config
        if (!config('advanced_email.scheduling.recurring.enabled', true)) {
            \Log::info('Recurring emails are disabled in configuration');
            return 0;
        }
        
        $count = 0;
        
        // Find recently sent recurring emails that need next occurrence
        $recentlySent = self::where('status', 'sent')
            ->whereNotNull('frequency')
            ->whereNull('parent_id') // Only process root emails to avoid duplication
            ->orWhere(function($query) {
                // Include emails with parent_id but no children yet
                $query->whereNotNull('parent_id')
                      ->whereNotNull('frequency')
                      ->whereDoesntHave('children');
            })
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get();
            
        foreach ($recentlySent as $email) {
            try {
                $nextOccurrence = $email->createNextOccurrence();
                
                if ($nextOccurrence) {
                    $count++;
                }
            } catch (\Exception $e) {
                \Log::error('Error creating next occurrence for recurring email', [
                    'uuid' => $email->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $count;
    }

    /**
     * Create a new instance of the scheduled email for the next occurrence.
     *
     * @return self|null
     */
    public function createNextOccurrence(): ?self
    {
        // If this is not a recurring email, don't create next occurrence
        if (empty($this->frequency)) {
            return null;
        }
        
        // Check if recurring emails are enabled in config
        if (!config('advanced_email.scheduling.recurring.enabled', true)) {
            \Log::info('Recurring emails are disabled in configuration');
            return null;
        }
        
        // Check if this specific frequency is enabled in config
        $frequencyEnabled = config('advanced_email.scheduling.recurring.frequencies.' . $this->frequency, true);
        if (!$frequencyEnabled) {
            \Log::info("Recurring emails with frequency '{$this->frequency}' are disabled in configuration");
            return null;
        }
        
        $nextDate = $this->calculateNextOccurrence();
        
        if (!$nextDate) {
            \Log::warning('Failed to calculate next occurrence for recurring email', [
                'uuid' => $this->uuid,
                'frequency' => $this->frequency,
                'frequency_options' => $this->frequency_options,
            ]);
            return null;
        }

        // Check if we've reached the end of recurrence
        if ($this->expires_at && $nextDate > $this->expires_at) {
            \Log::info('Recurring email has reached expiration date, no more occurrences will be created', [
                'uuid' => $this->uuid,
                'expires_at' => $this->expires_at,
                'next_calculated_date' => $nextDate,
            ]);
            return null;
        }
        
        // Check if we've reached max occurrences (if specified in frequency_options)
        $options = $this->frequency_options ?? [];
        $maxOccurrences = $options['max_occurrences'] ?? config('advanced_email.scheduling.recurring.max_occurrences_default', 100);
        
        if (is_numeric($maxOccurrences)) {
            // If this email has a parent, count all siblings in the same chain
            if ($this->parent_id) {
                $rootParentId = $this->parent()->exists() ? $this->parent->parent_id ?? $this->parent_id : $this->parent_id;
                $occurrenceCount = ScheduledEmail::where(function($query) use ($rootParentId) {
                    $query->where('id', $rootParentId)
                          ->orWhere('parent_id', $rootParentId);
                })->count();
            } else {
                // Count this email and all its children
                $occurrenceCount = ScheduledEmail::where(function($query) {
                    $query->where('id', $this->id)
                          ->orWhere('parent_id', $this->id);
                })->count();
            }
                
            if ($occurrenceCount >= (int)$maxOccurrences) {
                \Log::info('Recurring email has reached maximum occurrences', [
                    'uuid' => $this->uuid,
                    'max_occurrences' => $maxOccurrences,
                    'current_count' => $occurrenceCount,
                ]);
                return null;
            }
        }

        // Create a new scheduled email with the same properties but new date
        $newScheduled = $this->replicate(['sent_at', 'error', 'retry_attempts']);
        $newScheduled->uuid = (string) Str::uuid();
        $newScheduled->status = 'pending';
        $newScheduled->scheduled_at = $nextDate;
        $newScheduled->sent_at = null;
        $newScheduled->error = null;
        $newScheduled->retry_attempts = 0;
        $newScheduled->parent_id = $this->id; // Track parent-child relationship
        $newScheduled->occurrence_number = ($this->occurrence_number ?? 1) + 1; // Track occurrence number
        $newScheduled->save();
        
        \Log::info('Created next occurrence for recurring email', [
            'original_uuid' => $this->uuid,
            'new_uuid' => $newScheduled->uuid,
            'scheduled_at' => $nextDate,
            'frequency' => $this->frequency,
            'occurrence_number' => $newScheduled->occurrence_number,
        ]);
        
        return $newScheduled;
    }
}