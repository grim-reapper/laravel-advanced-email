<?php

namespace GrimReapper\AdvancedEmail\Services;

use Closure;
use Illuminate\Contracts\Mail\Mailer as MailerContract; // Keep for potential type hints elsewhere if needed, or remove if unused
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log; // Keep Log facade
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use DOMDocument;
use DOMXPath;
use GrimReapper\AdvancedEmail\Contracts\EmailBuilder as EmailBuilderContract;
use GrimReapper\AdvancedEmail\Events\EmailFailed;
use GrimReapper\AdvancedEmail\Events\EmailSending;
use GrimReapper\AdvancedEmail\Events\EmailSent;
use GrimReapper\AdvancedEmail\Jobs\SendEmailJob;
use GrimReapper\AdvancedEmail\Jobs\ProcessScheduledEmailsJob;
use GrimReapper\AdvancedEmail\Mail\GenericMailable;
use GrimReapper\AdvancedEmail\Models\EmailLink;
use GrimReapper\AdvancedEmail\Models\EmailTemplate;
use GrimReapper\AdvancedEmail\Models\EmailTemplateVersion;
use GrimReapper\AdvancedEmail\Models\ScheduledEmail;

class EmailService implements EmailBuilderContract
{
    use Macroable;

    protected MailManager $mailer;
    protected array $config;
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected ?array $from = null;
    protected ?string $subject = null;
    protected ?string $view = null;
    protected array $viewData = [];
    protected ?string $htmlContent = null;
    protected array $attachments = [];
    protected array $rawAttachments = [];
    protected array $storageAttachments = [];
    protected ?string $mailerName = null;
    protected array $placeholders = [];
    protected array $placeholderPatterns = [];
    protected ?string $templateName = null; // Added property to store template name
    protected bool $isContentFromDatabaseTemplate = false;
    
    // Scheduling properties
    protected ?\DateTime $scheduledAt = null;
    protected ?\DateTime $expiresAt = null;
    protected ?string $frequency = null;
    protected array $frequencyOptions = [];
    protected array $conditions = [];
    protected int $retryAttempts = 0;

    public function __construct(MailManager $mailer, array $config)
    {
        $this->mailer = $mailer;
        $this->config = $config;
        $this->mailerName = config('mail.default'); // Default to Laravel's default mailer
        $this->registerDefaultPlaceholderPatterns();
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->addRecipients('to', $address, $name);
        return $this;
    }

    /**
     * Set the email template to use by its unique name.
     *
     * @param string $templateName The unique name of the email template.
     * @return static
     */
    public function template(string $templateName): static
    {
        $this->templateName = $templateName;
        // Reset view/html if template is set, template takes precedence initially
        $this->view = null;
        $this->htmlContent = null;
        $this->viewData = [];
        // Subject might be overridden by template, clear it here or handle in buildMailable
        // $this->subject = null;
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->addRecipients('cc', $address, $name);
        return $this;
    }


    /**
     * Set the number of retry attempts for failed scheduled emails.
     *
     * @param int $attempts The number of retry attempts
     * @return static
     */
    public function retryAttempts(int $attempts): static
    {
        $this->retryAttempts = $attempts;
        return $this;
    }

    /**
     * Save the email as a scheduled email in the database.
     *
     * @return \GrimReapper\AdvancedEmail\Models\ScheduledEmail
     */
    public function saveScheduled(): \GrimReapper\AdvancedEmail\Models\ScheduledEmail
    {
        // Ensure we have a scheduled time
        if (!$this->scheduledAt) {
            throw new \InvalidArgumentException('Scheduled time must be set using schedule() method');
        }

        // Create the scheduled email record
        $scheduledEmail = new ScheduledEmail([
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
            'scheduled_at' => $this->scheduledAt,
            'expires_at' => $this->expiresAt,
            'retry_attempts' => $this->retryAttempts,
            'frequency' => $this->frequency,
            'frequency_options' => $this->frequencyOptions,
            'conditions' => $this->conditions,
            'mailer' => $this->mailerName,
            'from' => $this->from,
            'to' => $this->formatRecipients($this->to),
            'cc' => $this->formatRecipients($this->cc),
            'bcc' => $this->formatRecipients($this->bcc),
            'subject' => $this->subject,
            'template_name' => $this->templateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->placeholders,
            'attachments' => $this->prepareAttachmentsForStorage(),
        ]);

        $scheduledEmail->save();
        return $scheduledEmail;
    }

    /**
     * Format recipients for storage in the database.
     *
     * @param array $recipients
     * @return array
     */
    protected function formatRecipients(array $recipients): array
    {
        return array_map(function ($recipient) {
            return [
                'address' => $recipient['address'],
                'name' => $recipient['name'] ?? null,
            ];
        }, $recipients);
    }

    /**
     * Prepare attachments for storage in the database.
     *
     * @return array
     */
    protected function prepareAttachmentsForStorage(): array
    {
        $result = [];
        
        // Process regular attachments
        foreach ($this->attachments as $attachment) {
            $result[] = [
                'type' => 'file',
                'path' => $attachment['file'],
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        // Process raw attachments
        foreach ($this->rawAttachments as $attachment) {
            $result[] = [
                'type' => 'raw',
                'data' => base64_encode($attachment['data']),
                'name' => $attachment['name'],
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        // Process storage attachments
        foreach ($this->storageAttachments as $attachment) {
            $result[] = [
                'type' => 'storage',
                'path' => $attachment['path'],
                'options' => $attachment['options'] ?? [],
            ];
        }
        
        return $result;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->addRecipients('bcc', $address, $name);
        return $this;
    }

    public function from(string $address, ?string $name = null): static
    {
        $this->from = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->isContentFromDatabaseTemplate = false;
        $this->view = $view;
        $this->viewData = $data;
        $this->htmlContent = null; // View takes precedence
        return $this;
    }

    public function html(string $htmlContent, array $placeholders = []): static
    {
        $this->isContentFromDatabaseTemplate = false;
        $this->htmlContent = $htmlContent;
        $this->placeholders = $placeholders;
        $this->view = null; // HTML content takes precedence
        $this->viewData = [];
        return $this;
    }

    public function registerPlaceholderPattern(string $pattern, ?callable $callback = null): static
    {
        $this->placeholderPatterns[] = ['pattern' => $pattern, 'callback' => $callback];
        return $this;
    }

    public function attach(string $file, array $options = []): static
    {
        // Check if the file exists before attempting to attach
        if (!file_exists($file)) {
            // Log a warning if the file doesn't exist
            Log::warning("Attachment file not found and skipped: {$file}");
            // Return the instance to allow chaining without adding the attachment
            return $this;
        }
        $this->attachments[] = ['file' => $file, 'options' => $options];
        return $this;
    }

    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->rawAttachments[] = ['data' => $data, 'name' => $name, 'options' => $options];
        return $this;
    }

    public function attachFromStorage(string $disk, string $path, ?string $name = null, array $options = []): static
    {
        $this->storageAttachments[] = [
            'disk' => $disk,
            'path' => $path,
            'name' => $name,
            'options' => $options
        ];
        return $this;
    }

    public function mailer(string $mailer): static
    {
        $this->mailerName = $mailer;
        return $this;
    }
    
    /**
     * Set placeholder values for the email template.
     *
     * @param array $placeholders Key-value pairs of placeholder values
     * @return static
     */
    public function with(array $placeholders): static
    {
        $this->placeholders = array_merge($this->placeholders, $placeholders);
        return $this;
    }
    
    /**
     * Schedule the email to be sent at a specific time.
     *
     * @param \DateTime|string $scheduledAt When to send the email
     * @return static
     */
    public function scheduleAt($scheduledAt): static
    {
        if (is_string($scheduledAt)) {
            $scheduledAt = new \DateTime($scheduledAt);
        }
        
        $this->scheduledAt = $scheduledAt;
        return $this;
    }
    
    /**
     * Set up a recurring schedule for the email.
     *
     * @param string $frequency The frequency (daily, weekly, monthly, custom)
     * @param array $options Additional options for the frequency
     * @return static
     */
    public function recurring(string $frequency, array $options = []): static
    {
        $this->frequency = $frequency;
        $this->frequencyOptions = $options;
        return $this;
    }
    
    /**
     * Add a condition for sending the email.
     *
     * @param string $type The type of condition
     * @param mixed $value The condition value
     * @return static
     */
    public function when(string $type, $value): static
    {
        $this->conditions[] = [
            'type' => $type,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Schedule the email for future delivery.
     *
     * @param \DateTimeInterface|\Carbon\Carbon $dateTime The date and time to send the email
     * @return static
     */
    public function schedule(\DateTimeInterface|\Carbon\Carbon|string $dateTime): static
    {
        // Ensure we have required data
        if (empty($this->to)) {
            throw new \InvalidArgumentException('Recipients are required for scheduling an email');
        }

        if (is_string($dateTime)) {
            $this->scheduledAt = new \DateTime($dateTime);
        } elseif ($dateTime instanceof \DateTimeInterface) {
            $this->scheduledAt = \DateTime::createFromInterface($dateTime);
        } elseif($dateTime instanceof \Carbon\Carbon) {
            $this->scheduledAt = $dateTime->toDateTime();
        }else{
            $this->scheduledAt = $dateTime;
        }
        
        return $this;
    }

    /**
     * Set the expiration date for the scheduled email.
     *
     * @param  \DateTimeInterface|\Carbon\Carbon|string|null  $dateTime
     * @return static
     */
    public function expires(\DateTimeInterface|\Carbon\Carbon|string|null $dateTime): static
    {
        if (is_string($dateTime)) {
            $this->expiresAt = new \DateTime($dateTime);
        } elseif ($dateTime instanceof \DateTimeInterface) {
            $this->expiresAt = \DateTime::createFromInterface($dateTime);
        } elseif($dateTime instanceof \Carbon\Carbon) {
            $this->expiresAt = $dateTime->toDateTime();
        }else{
            $this->expiresAt = null;
        }
       
        return $this;
    }


    public function send(?Mailable $mailable = null): void
    {
        // If scheduled for future, store in database instead of sending immediately
        if ($this->scheduledAt && $this->scheduledAt > now()) {
            $this->schedule();
            return;
        }
        
        // Generate a UUID for logging purposes
        $logUuid = (string) Str::uuid();

        // Create initial log entry in the database before building the mailable
        $logEntry = \GrimReapper\AdvancedEmail\Models\EmailLog::create([
            'uuid' => $logUuid,
            'status' => 'pending', // Initial status
            'mailer' => $this->mailerName,
            'from' => $this->from,
            'to' => $this->formatRecipients($this->to),
            'cc' => $this->formatRecipients($this->cc),
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'template_name' => $this->templateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->placeholders,
            'attachments' => $this->prepareAttachmentsForStorage(),
            // Add other relevant fields if necessary
            'scheduled_at' => $this->scheduledAt, // Include scheduled_at for consistency, even if null
            'expires_at' => $this->expiresAt, // Include expires_at for consistency, even if null
        ]);

        $mailable = $this->buildMailable($logUuid);
        
        // Prepare event data
        $eventData = [
            'uuid' => $logUuid,
            'mailable' => $mailable,
            'mailer' => $this->mailerName,
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'template_name' => $this->templateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->placeholders,
            'attachments' => $this->prepareAttachmentsForStorage(),
            'sent_at' => now(),
            'status' => 'sending'
        ];
        
        // Dispatch EmailSending event before attempting to send
        Event::dispatch(new EmailSending($eventData));
        
        // Send email immediately using the configured mailer
        try {
            $this->mailer->mailer($this->mailerName)->send($mailable);
            Log::info("Email sent successfully via provider: {$this->mailerName}", ['logUuid' => $logUuid]);
            Event::dispatch(new EmailSent($eventData));
        } catch (\Exception $e) {
            Log::error("Failed to send email via provider: {$this->mailerName}. Error: {$e->getMessage()}", [
                'logUuid' => $logUuid,
                'exception' => $e
            ]);
            Event::dispatch(new EmailFailed(array_merge($eventData, ['exception' => $e])));
            throw $e;
        } finally {
            $this->resetState();
        }
    }
    
    /**
     * Dispatch the email job for immediate sending.
     *
     * @param Mailable $mailable The mailable to be sent
     * @return void
     */
    protected function dispatchJob(Mailable $mailable): void
    {
        // Generate a UUID for logging purposes
        $logUuid = (string) Str::uuid();
        
        // Create and dispatch the job
        $job = new SendEmailJob($mailable, $this->mailerName, $logUuid);
        dispatch($job);
        
        // Reset the email builder state after sending
        $this->resetState();
    }

    public function queue(?string $connection = null, ?string $queue = null): void
    {
        // If scheduled for future, store in database instead of queueing immediately
        if ($this->scheduledAt && $this->scheduledAt > now()) {
            $this->schedule();
            return;
        }
        
        // Note: Tracking pixel injection needs the log UUID.
        // For queued jobs, the log entry might not exist when the email is built.
        // We generate the UUID here and pass it to the job.
        // The job will be responsible for including it in the log data when it runs.
        $logUuid = (string) Str::uuid();

        // Create initial log entry in the database before building the mailable
        $logEntry = \GrimReapper\AdvancedEmail\Models\EmailLog::create([
            'uuid' => $logUuid,
            'status' => 'pending', // Initial status
            'mailer' => $this->mailerName,
            'from' => $this->from,
            'to' => $this->formatRecipients($this->to),
            'cc' => $this->formatRecipients($this->cc),
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'template_name' => $this->templateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->placeholders,
            'attachments' => $this->prepareAttachmentsForStorage(),
            // Add other relevant fields if necessary
            'scheduled_at' => $this->scheduledAt, // Include scheduled_at for consistency, even if null
            'expires_at' => $this->expiresAt, // Include expires_at for consistency, even if null
        ]);

        $mailable = $this->buildMailable($logUuid);

        // Pass the logUuid to the job so it can be logged when the job runs.
        $job = new SendEmailJob($mailable, $this->mailerName, $logUuid);

        if ($connection) {
            $job->onConnection($connection);
        }
        if ($queue) {
            $job->onQueue($queue);
        }

        // Dispatch job first, events will be handled by the job/listener
        dispatch($job);
        $this->resetState();
    }

    protected function registerDefaultPlaceholderPatterns(): void
    {
        // Pattern for {{ placeholder }}
        $this->registerPlaceholderPattern('/\{\{\s*([\w.-]+)\s*\}\}/');
        // Pattern for ##placeholder## - Avoid matching CSS hex colors like #ffffff
        // Looks for ## followed by word chars/dot/hyphen, not preceded by # or hex chars
        $this->registerPlaceholderPattern('/(?<![#\da-fA-F])##([\w.-]+)##/');
        // Pattern for [[placeholder]]
        $this->registerPlaceholderPattern('/\[\[\s*([\w.-]+)\s*\]\]/');
    }

    protected function processPlaceholders(string $content): string
    {
        if (empty($this->placeholders) || empty($this->placeholderPatterns)) {
            return $content;
        }

        foreach ($this->placeholderPatterns as $patternData) {
            $pattern = $patternData['pattern'];
            $callback = $patternData['callback'];

            if ($callback && is_callable($callback)) {
                // Use custom callback for replacement if provided
                $content = preg_replace_callback($pattern, function ($matches) use ($callback) {
                    $placeholderName = $matches[1] ?? null; // Get captured group
                    if ($placeholderName === null) return $matches[0]; // No capture group, return original match
                    return $callback($placeholderName, $this->placeholders[$placeholderName] ?? $matches[0], $this->placeholders);
                }, $content);
            } else {
                // Default replacement logic
                $content = preg_replace_callback($pattern, function ($matches) {
                    $placeholderName = $matches[1] ?? null; // Get captured group
                    if ($placeholderName === null) return $matches[0]; // No capture group, return original match
                    // Replace with value if exists, otherwise keep original placeholder
                    return $this->placeholders[$placeholderName] ?? $matches[0];
                }, $content);
            }
        }

        return $content;
    }

    protected function buildMailable(?string $logUuid = null): Mailable // Add logUuid parameter, make nullable for previews
    {
        $mailable = new GenericMailable();

        if ($this->from) {
            $mailable->from($this->from['address'], $this->from['name']);
        }

        foreach ($this->to as $recipient) {
            $mailable->to($recipient['address'], $recipient['name']);
        }
        foreach ($this->cc as $recipient) {
            $mailable->cc($recipient['address'], $recipient['name']);
        }
        foreach ($this->bcc as $recipient) {
            $mailable->bcc($recipient['address'], $recipient['name']);
        }

        // Load template if name is provided *before* processing placeholders
        if ($this->templateName) {
            $this->loadTemplateData($this->templateName);
        }

        // Process placeholders for subject and HTML content *after* potentially loading from template
        $processedSubject = $this->subject ? $this->processPlaceholders($this->subject) : null;
        $processedHtmlContent = $this->htmlContent ? $this->processPlaceholders($this->htmlContent) : null;
        
        if ($processedSubject) {
            $mailable->subject($processedSubject);
        }
        
        if ($this->view) {
            // Render the view to HTML content for processing
            $processedHtmlContent = view($this->view, $this->viewData)->render();
            // Process any placeholders in the rendered content
            $processedHtmlContent = $this->processPlaceholders($processedHtmlContent);
        }

        foreach ($this->attachments as $attachment) {
            $mailable->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $mailable->attachData($attachment['data'], $attachment['name'], $attachment['options']);
        }

        foreach ($this->storageAttachments as $attachment) {
            $mailable->attachFromStorageDisk(
                $attachment['disk'],
                $attachment['path'],
                $attachment['name'],
                $attachment['options']
            );
        }

        // Render Blade content if htmlContent is set
        if ($processedHtmlContent) {
            try {
                // Parse HTML content properly to maintain structure
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                // Load HTML with proper encoding and flags to maintain structure
                $dom->loadHTML(mb_convert_encoding($processedHtmlContent, 'HTML-ENTITIES', 'UTF-8'), 
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                
                // Convert back to string while preserving structure
                $processedHtmlContent = $dom->saveHTML();
                
                // Now render through Blade
                $renderedHtml = $processedHtmlContent; // Default to processed content
                if (!$this->isContentFromDatabaseTemplate && $processedHtmlContent) { // Only Blade render if not from DB template AND content exists
                    $renderedHtml = Blade::render($processedHtmlContent, $this->viewData, true); // Pass true to delete cache
                }
                
                // Process links for tracking if enabled and we have a log UUID
                if ($logUuid && config('advanced_email.tracking.clicks.enabled')) {
                    $renderedHtml = $this->processLinksForTracking($renderedHtml, $logUuid);
                }
                // dd(route('advanced_email.tracking.opens', ['uuid' => $logUuid]));
                    
                // Inject tracking pixel if enabled, route is configured, and we have a UUID (i.e., not a preview)
                if ($logUuid && config('advanced_email.tracking.opens.enabled') && ($openTrackingRouteName = config('advanced_email.tracking.opens.route_name'))) {
                    // Ensure the route name exists or handle URL generation appropriately
                    // Construct the full route name using the group prefix
                    $fullOpenTrackingRoute = 'advanced_email.tracking.' . $openTrackingRouteName;
                    try {
                        $pixelUrl = route($fullOpenTrackingRoute, ['uuid' => $logUuid]);
                        $trackingPixel = '<img src="' . $pixelUrl . '" alt="" width="1" height="1" style="display:none;border:0;" />';
                        // Append pixel to the end of the body, or handle more robustly if needed
                        if (stripos($renderedHtml, '</body>') !== false) {
                            $renderedHtml = str_ireplace('</body>', $trackingPixel . '</body>', $renderedHtml);
                        } else {
                            $renderedHtml .= $trackingPixel; // Fallback if </body> tag is not found
                        }
                    } catch (\InvalidArgumentException $e) {
                        Log::error("Could not generate tracking pixel URL. Route '{$fullOpenTrackingRoute}' not defined or missing parameters?", ['uuid' => $logUuid]);
                    }
                }
                $mailable->html($renderedHtml);
            } catch (\Throwable $e) {
                Log::error("Blade rendering failed for template '{$this->templateName}': " . $e->getMessage());
                // Fallback or rethrow? For now, log and potentially send raw content if needed
                // $mailable->html($processedHtmlContent); // Or maybe throw an exception
                throw $e; // Rethrow to make the failure explicit
            }
        }

        return $mailable;
    }

    /**
     * Preview the email content without sending.
     *
     * @return string|null The rendered HTML content or null on failure.
     * @throws \Throwable If template loading or rendering fails.
     */
    public function preview(): void
    {
        // Load template if name is provided
        if ($this->templateName) {
            $this->loadTemplateData($this->templateName);
        }

        // Process placeholders for HTML content *after* potentially loading from template
        $processedHtmlContent = $this->htmlContent ? $this->processPlaceholders($this->htmlContent) : null;
        
        if ($this->view) {
            // Render view if specified (takes precedence)
            try {
                echo View::make($this->view, $this->viewData)->render();
            } catch (\Throwable $e) {
                Log::error("Blade view rendering failed for view '{$this->view}': " . $e->getMessage());
                throw $e;
            }
        } elseif ($processedHtmlContent) {
            // Render Blade content from database template
            try {
                // Parse HTML content properly to maintain structure
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                // Load HTML with proper encoding and flags to maintain structure
                $dom->loadHTML(mb_convert_encoding($processedHtmlContent, 'HTML-ENTITIES', 'UTF-8'), 
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                
                // Convert back to string while preserving structure
                $processedHtmlContent = $dom->saveHTML();
                
                // Now render through Blade
                $renderedHtml = $processedHtmlContent; // Default to processed content
                if (!$this->isContentFromDatabaseTemplate && $processedHtmlContent) { // Only Blade render if not from DB template AND content exists
                    $renderedHtml = Blade::render($processedHtmlContent, $this->viewData, true); // Pass true to delete cache
                }

                // Link processing and tracking pixel injection are skipped in preview mode.

                $this->resetState(); // Reset state after preview
                echo $renderedHtml;
            } catch (\Throwable $e) {
                Log::error("Blade rendering failed for template '{$this->templateName}' during preview: " . $e->getMessage());
                $this->resetState(); // Reset state even on failure
                throw $e; // Rethrow to make the failure explicit
            }
        } else {
            Log::warning("Preview requested but no view or HTML content is available.");
            $this->resetState();
        }
    }

    protected function addRecipients(string $type, string|array $address, ?string $name = null): void
    {
        if (is_array($address)) {
            foreach ($address as $key => $value) {
                if (is_string($key)) {
                    // ['email' => 'name'] format
                    $this->{$type}[] = ['address' => $key, 'name' => $value];
                } else {
                    // ['email1', 'email2'] format
                    $this->{$type}[] = ['address' => $value, 'name' => null];
                }
            }
        } else {
            // Single email string format
            $this->{$type}[] = ['address' => $address, 'name' => $name];
        }
    }

    protected function prepareLogData(Mailable $mailable, ?string $logUuid = null): array // Add logUuid parameter
    {
        // This method gathers data *before* sending for logging purposes
        // Actual logging happens in an event listener based on config
        $attachments = collect($this->attachments)->pluck('file')
            ->merge(collect($this->rawAttachments)->pluck('name'))
            ->merge(collect($this->storageAttachments)->map(fn($att) => $att['name'] ?? basename($att['path'])))
            ->all();

        return [
            'mailer' => $this->mailerName,
            'from' => json_encode($this->from ?? config('mail.from')),
            'to' => json_encode($this->to),
            'cc' => json_encode($this->cc),
            'bcc' => json_encode($this->bcc),
            'subject' => $this->subject, // Log original subject before processing
            'template_name' => $this->templateName, // Add template name
            'view' => $this->view,
            'html_content' => $this->htmlContent, // Log original content before processing
            'view_data' => !empty($this->viewData) ? json_encode($this->viewData) : null,
            'placeholders' => !empty($this->placeholders) ? json_encode($this->placeholders) : null,
            'attachments' => !empty($attachments) ? json_encode($attachments) : null,
            'sent_at' => now(),
            'status' => 'pending', // Initial status, updated by listeners
            'error' => null,
            'uuid' => $logUuid ?? (string) Str::uuid(), // Ensure UUID is always set
        ];
    }

    /**
     * Load email content (subject, html, placeholders) from the database based on template name.
     *
     * @param string $templateName
     * @return void
     */
    protected function loadTemplateData(string $templateName): void
    {
        try {
            $template = EmailTemplate::where('name', $templateName)->firstOrFail();
            $activeVersion = $template->activeVersion;

            if (!$activeVersion) {
                Log::warning("No active version found for email template: {$templateName}");
                // Decide how to handle: throw exception, use fallback, or do nothing?
                // For now, we'll log and proceed, potentially sending an empty email if no other content is set.
                return;
            }

            // Only override if not explicitly set *after* calling template()
            if ($this->subject === null) {
                $this->subject = $activeVersion->subject;
            }
            if ($this->htmlContent === null && $this->view === null) {
                $this->htmlContent = $activeVersion->html_content;
                $this->isContentFromDatabaseTemplate = true;
                // Optionally load text content if needed by GenericMailable or config
                // $this->textContent = $activeVersion->text_content;
            }
            // Merge placeholders defined in template with those set manually, manual ones take precedence
            $this->placeholders = array_merge($activeVersion->placeholders ?? [], $this->placeholders);

        } catch (ModelNotFoundException $e) {
            Log::error("Email template not found: {$templateName}");
            // Decide how to handle: throw exception, use fallback, or do nothing?
            // For now, log the error and let the process continue (might fail later if no view/html is set)
        } catch (\Throwable $e) {
            Log::error("Error loading email template '{$templateName}': " . $e->getMessage());
            // Handle other potential errors during template loading
        }
    }

    /**
     * Process HTML content to replace links with tracking URLs.
     *
     * @param string $htmlContent
     * @param string $logUuid
     * @return string
     */
    protected function processLinksForTracking(string $htmlContent, string $logUuid): string
    {
        if (empty($htmlContent)) {
            return $htmlContent;
        }

        // Suppress errors for invalid HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Load HTML, ensuring proper encoding
        $dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');

        $logId = null; // Lazy load log ID if needed

        foreach ($links as $link) {
            $originalUrl = $link->getAttribute('href');
            
            // Skip empty, mailto, tel, or anchor links
            if (empty($originalUrl) || str_starts_with($originalUrl, '#') || str_starts_with($originalUrl, 'mailto:') || str_starts_with($originalUrl, 'tel:')) {
                continue;
            }

            // Find the email log ID associated with the logUuid
            if ($logId === null) {
                $logTable = config('advanced_email.database.tables.email_logs', 'email_logs');
                $connection = config('advanced_email.database.connection');
                $logEntry = DB::connection($connection)->table($logTable)->where('uuid', $logUuid)->first(['id']);
                if (!$logEntry) {
                    Log::error("Could not find email log entry for UUID {$logUuid} while processing links.");
                    continue; // Skip link processing if log entry not found
                }
                $logId = $logEntry->id;
            }
            
            // Validate URL scheme
            $scheme = parse_url($originalUrl, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme ?? ''), ['http', 'https'])) {
                Log::warning("Invalid or disallowed URL scheme in link: {$originalUrl}");
                continue; // Skip this link
            }
            
            $linkUuid = (string) Str::uuid();
            // Store the link information
            try {
                EmailLink::create([
                    'uuid' => $linkUuid,
                    'email_log_id' => $logId,
                    'original_url' => $originalUrl,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to store email link tracking data for log UUID {$logUuid}: " . $e->getMessage());
                continue; // Skip replacing this link if DB write fails
            }

            // Generate the tracking URL
            try {
                // Use the configured route name for clicks and prepend the group prefix
                $clickTrackingRouteName = config('advanced_email.tracking.clicks.route_name');
                $fullClickTrackingRoute = 'advanced_email.tracking.' . $clickTrackingRouteName;
                // Note: The route definition uses {uuid} and {link_id}, not linkUuid
                $trackingUrl = route($fullClickTrackingRoute, ['uuid' => $logUuid, 'link_id' => $linkUuid]);
                $link->setAttribute('href', $trackingUrl);
            } catch (\InvalidArgumentException $e) {
                Log::error("Could not generate tracking click URL. Route '" . $fullClickTrackingRoute . "' not defined or missing parameters?", ['uuid' => $logUuid, 'link_id' => $linkUuid]);
                // Continue without replacing the link if route generation fails
            }
        }

        // Save the modified HTML
        return $dom->saveHTML() ?: $htmlContent; // Fallback to original content on save failure
    }

    protected function resetState(): void
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->from = null;
        $this->subject = null;
        $this->view = null;
        $this->viewData = [];
        $this->htmlContent = null;
        $this->attachments = [];
        $this->rawAttachments = [];
        $this->storageAttachments = [];
        $this->mailerName = config('mail.default');
        $this->placeholders = [];
        $this->templateName = null; // Reset template name
        $this->isContentFromDatabaseTemplate = false;
        
        // Reset scheduling properties
        $this->scheduledAt = null;
        $this->expiresAt = null;
        $this->frequency = null;
        $this->frequencyOptions = [];
        $this->conditions = [];
        
        // Keep custom patterns, but reset defaults if needed or clear all
        // $this->placeholderPatterns = [];
        // $this->registerDefaultPlaceholderPatterns(); // Reset to default mailer
    }
}