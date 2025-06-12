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
use GrimReapper\AdvancedEmail\Services\AttachmentManager;
use GrimReapper\AdvancedEmail\Services\TemplateProcessor; 

class EmailService implements EmailBuilderContract
{
    use Macroable;

    protected MailManager $mailer;
    protected array $config;
    protected AttachmentManager $attachmentManager;
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected ?array $from = null;
    protected ?string $subject = null; // Stays as direct input, passed to TemplateProcessor
    protected ?string $view = null;
    protected array $viewData = [];
    protected ?string $htmlContent = null; // Stays as direct input, passed to TemplateProcessor
    protected ?string $mailerName = null;
    protected TemplateProcessor $templateProcessor; // New property
    protected ?string $processedDbTemplateName = null; // New property for logging
    
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
        $this->attachmentManager = new AttachmentManager();
        $this->templateProcessor = new TemplateProcessor(); // Instantiate new processor
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
        $this->templateProcessor->setTemplateName($templateName);
        $this->view = null; 
        $this->viewData = [];
        $this->htmlContent = null; 
        $this->subject = null; // Subject might be loaded from template
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
        if (empty($this->from)) {
            $defaultFrom = config('mail.from', ['address' => null, 'name' => null]);
            $this->from = [
                'address' => $defaultFrom['address'] ?? null,
                'name' => $defaultFrom['name'] ?? null
            ];
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
            'template_name' => $this->processedDbTemplateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->templateProcessor->getPlaceholders(),
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
        return $this->attachmentManager->prepareForStorage();
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->addRecipients('bcc', $address, $name);
        return $this;
    }

    public function from(string $address, ?string $name = null): static
    {
        $this->from = ['address' => $address, 'name' => $name];
        if (empty($this->from)) {
            $defaultFrom = config('mail.from', ['address' => null, 'name' => null]);
            $this->from = [
                'address' => $defaultFrom['address'] ?? null,
                'name' => $defaultFrom['name'] ?? null
            ];
        }
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject; // Store on EmailService as the primary input for subject
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data); // Allow merging viewData
        $this->htmlContent = null; // View overrides direct HTML
        $this->templateProcessor->setTemplateName(null); // View overrides DB template for body
        return $this;
    }

    public function html(string $htmlContent, array $placeholders = []): static
    {
        $this->htmlContent = $htmlContent; // Store on EmailService as primary input for HTML
        if (!empty($placeholders)) {
            $this->templateProcessor->addPlaceholders($placeholders);
        }
        $this->view = null; // Direct HTML overrides view
        $this->viewData = []; // Also clear viewData
        $this->templateProcessor->setTemplateName(null); // Direct HTML overrides DB template
        return $this;
    }

    public function registerPlaceholderPattern(string $pattern, ?callable $callback = null): static
    {
        $this->templateProcessor->registerPattern($pattern, $callback);
        return $this;
    }

    public function attach(string $file, array $options = []): static
    {
        $this->attachmentManager->addFile($file, $options);
        return $this;
    }

    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->attachmentManager->addData($data, $name, $options);
        return $this;
    }

    public function attachFromStorage(string $disk, string $path, ?string $name = null, array $options = []): static
    {
        $this->attachmentManager->addFromStorage($disk, $path, $name, $options);
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
        $this->templateProcessor->addPlaceholders($placeholders);
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
            $this->schedule($this->scheduledAt);
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
            'template_name' => $this->processedDbTemplateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->templateProcessor->getPlaceholders(),
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
            'template_name' => $this->processedDbTemplateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->templateProcessor->getPlaceholders(),
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
            Event::dispatch(new EmailFailed($eventData, $e));
            throw $e;
        } finally {
            $this->resetState();
        }
    }

    public function queue(?string $connection = null, ?string $queue = null): void
    {
        // If scheduled for future, store in database instead of queueing immediately
        if ($this->scheduledAt && $this->scheduledAt > now()) {
            $this->schedule($this->scheduledAt);
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
            'template_name' => $this->processedDbTemplateName,
            'view' => $this->view,
            'html_content' => $this->htmlContent,
            'view_data' => $this->viewData,
            'placeholders' => $this->templateProcessor->getPlaceholders(),
            'attachments' => $this->prepareAttachmentsForStorage(),
            // Add other relevant fields if necessary
            'scheduled_at' => $this->scheduledAt, // Include scheduled_at for consistency, even if null
            'expires_at' => $this->expiresAt, // Include expires_at for consistency, even if null
        ]);

        $mailable = $this->buildMailable($logUuid);

        // Pass the logUuid and selectedAbVariantId to the job
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

protected function buildMailable(?string $logUuid = null): Mailable
{
    // 1. Pass current EmailService state (direct subject/HTML) to TemplateProcessor
    //    This ensures TemplateProcessor knows about any subject() or html() calls.
    $this->templateProcessor->setDirectSubject($this->subject);
    $this->templateProcessor->setDirectHtmlContent($this->htmlContent);

    // 2. Process template (loads from DB if name set, applies placeholders to subject/body)
    $processedData = $this->templateProcessor->loadAndProcess();
    
    // 3. Update EmailService's own subject and processedDbTemplateName for logging/consistency.
    //    The $this->subject property will now hold the definitive subject for this email send.
    $currentSubject = $processedData->subject; 
    $this->subject = $currentSubject; 
    $this->processedDbTemplateName = $processedData->loadedTemplateName; 

    $finalHtmlBody = $processedData->htmlBody;
    $isFromDbRenderingFlag = $processedData->isFromDatabase;

    // 4. If a Blade view file was specified ($this->view is not null), it overrides HTML body.
    if ($this->view) {
        try {
            // Pass viewData from EmailService and any globally added placeholders from TemplateProcessor.
            $finalHtmlBody = View::make($this->view, array_merge($this->viewData, $this->templateProcessor->getPlaceholders()))->render();
            $isFromDbRenderingFlag = false; // Content is now from a Blade view file, not a DB template for rendering decision.
        } catch (\Throwable $e) {
            Log::error("Blade view rendering failed for view '{$this->view}': " . $e->getMessage());
            throw $e; 
        }
    }
    
    // 5. Instantiate GenericMailable and set basic properties
    $mailable = new GenericMailable();
    if ($this->from) {
        $mailable->from($this->from['address'], $this->from['name']);
    }
    // Simplified recipient handling (assuming addRecipients formats them correctly)
    foreach ($this->to as $recipient) { $mailable->to($recipient['address'], $recipient['name']); }
    foreach ($this->cc as $recipient) { $mailable->cc($recipient['address'], $recipient['name']); }
    foreach ($this->bcc as $recipient) { $mailable->bcc($recipient['address'], $recipient['name']); }

    if ($currentSubject) {
        $mailable->subject($currentSubject);
    }
    
    // 6. Attachments
    if ($this->attachmentManager->hasAttachments()) {
        $this->attachmentManager->attachToMailable($mailable);
    }

    // 7. Process and set HTML content for the mailable
    if ($finalHtmlBody) {
        try {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            // Use mb_convert_encoding to ensure proper handling of HTML entities with UTF-8
            $dom->loadHTML(mb_convert_encoding($finalHtmlBody, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $htmlForProcessing = $dom->saveHTML(); // This is the placeholder-processed HTML
            
            $renderedHtml = $htmlForProcessing;

            // Conditionally render with Blade:
            // - If content is NOT from a database template ($isFromDbRenderingFlag is false)
            // - AND we are NOT using a Blade view file ($this->view is null, meaning $finalHtmlBody came from direct input or DB template that allows further Blade processing)
            // - AND there is HTML content to render.
            if (!$isFromDbRenderingFlag && !$this->view && $htmlForProcessing) { 
                $allPlaceholdersForBlade = array_merge($this->viewData, $this->templateProcessor->getPlaceholders());
                $renderedHtml = Blade::render($htmlForProcessing, $allPlaceholdersForBlade, true);
            }
            
            // Link and Open Tracking
            if ($logUuid && config('advanced_email.tracking.clicks.enabled')) {
                $renderedHtml = $this->processLinksForTracking($renderedHtml, $logUuid);
            }
            if ($logUuid && config('advanced_email.tracking.opens.enabled') && ($openTrackingRouteName = config('advanced_email.tracking.opens.route_name'))) {
                $fullOpenTrackingRoute = 'advanced_email.tracking.' . $openTrackingRouteName;
                try {
                    $pixelUrl = route($fullOpenTrackingRoute, ['uuid' => $logUuid]);
                    $trackingPixel = '<img src="' . $pixelUrl . '" alt="" width="1" height="1" style="display:none;border:0;" />';
                    if (stripos($renderedHtml, '</body>') !== false) {
                        $renderedHtml = str_ireplace('</body>', $trackingPixel . '</body>', $renderedHtml);
                    } else {
                        $renderedHtml .= $trackingPixel;
                    }
                } catch (\InvalidArgumentException $e) {
                    Log::error("Could not generate tracking pixel URL. Route '{$fullOpenTrackingRoute}' not defined or missing parameters?", ['uuid' => $logUuid]);
                }
            }
            $mailable->html($renderedHtml);
        } catch (\Throwable $e) {
            $errorContext = $this->view ?? $this->processedDbTemplateName ?? 'direct HTML input';
            Log::error("Error processing or rendering HTML content for: " . $errorContext . ". Error: " . $e->getMessage());
            throw $e; 
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
        // 1. Pass current EmailService state (direct subject/HTML) to TemplateProcessor
        $this->templateProcessor->setDirectSubject($this->subject);
        $this->templateProcessor->setDirectHtmlContent($this->htmlContent);
        // Placeholders are already managed by templateProcessor via with()

        // 2. Process template (loads from DB if name set, applies placeholders to subject/body)
        $processedData = $this->templateProcessor->loadAndProcess();
        
        // Update EmailService's subject and processedDbTemplateName for context (e.g. error logging)
        // Although subject isn't directly outputted in preview, having it processed is consistent.
        $this->subject = $processedData->subject; 
        $this->processedDbTemplateName = $processedData->loadedTemplateName;

        $finalHtmlBody = $processedData->htmlBody;
        $isFromDbRenderingFlag = $processedData->isFromDatabase;

        // 3. If a Blade view file was specified, it overrides HTML body.
        if ($this->view) {
            try {
                // Pass combined viewData (from EmailService) and placeholders (from TemplateProcessor) to the view.
                $finalHtmlBody = View::make($this->view, array_merge($this->viewData, $this->templateProcessor->getPlaceholders()))->render();
                $isFromDbRenderingFlag = false; // Content is now from a Blade view file.
            } catch (\Throwable $e) {
                Log::error("Blade view rendering failed for preview of view '{$this->view}': " . $e->getMessage());
                $this->resetState(); // Reset state on error
                throw $e; 
            }
        }
        
        // 4. Process and output HTML content
        if ($finalHtmlBody) {
            try {
                // Ensure HTML structure (though less critical for echo than for mailable)
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                $dom->loadHTML(mb_convert_encoding($finalHtmlBody, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                $htmlForProcessing = $dom->saveHTML();
                
                $renderedHtml = $htmlForProcessing; // Default to this content

                // Conditionally render with Blade:
                // - If content is NOT from a database template (isFromDbRenderingFlag is false)
                // - AND we are NOT using a Blade view file ($this->view is null)
                // - AND there is HTML content to render.
                if (!$isFromDbRenderingFlag && !$this->view && $htmlForProcessing) { 
                    $allPlaceholdersForBlade = array_merge($this->viewData, $this->templateProcessor->getPlaceholders());
                    $renderedHtml = Blade::render($htmlForProcessing, $allPlaceholdersForBlade, true);
                }
                echo $renderedHtml;
            } catch (\Throwable $e) {
                $errorContext = $this->view ?? $this->processedDbTemplateName ?? 'direct HTML input';
                Log::error("Error processing or rendering HTML content for preview: " . $errorContext . ". Error: " . $e->getMessage());
                $this->resetState(); // Reset state on error
                throw $e; 
            }
        } else {
            Log::warning("Preview requested but no view or HTML content is available to render.");
        }
        
        $this->resetState(); // Reset state after successful preview or warning
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
        // $attachments = collect($this->attachments)->pluck('file')
        //     ->merge(collect($this->rawAttachments)->pluck('name'))
        //     ->merge(collect($this->storageAttachments)->map(fn($att) => $att['name'] ?? basename($att['path'])))
        //     ->all();

        return [
            'mailer' => $this->mailerName,
            'from' => json_encode($this->from ?? config('mail.from')),
            'to' => json_encode($this->to),
            'cc' => json_encode($this->cc),
            'bcc' => json_encode($this->bcc),
            'subject' => $this->subject, // This is the final subject after potential processing
            'template_name' => $this->processedDbTemplateName, // Log the name of the DB template if one was used
            'view' => $this->view,
            'html_content' => $this->htmlContent, // This is the original HTML input if any
            'view_data' => !empty($this->viewData) ? json_encode($this->viewData) : null,
            'placeholders' => !empty($this->templateProcessor->getPlaceholders()) ? json_encode($this->templateProcessor->getPlaceholders()) : null,
            'attachments' => !empty($this->attachmentManager->prepareForStorage()) ? json_encode($this->attachmentManager->prepareForStorage()) : null,
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
        $this->processedDbTemplateName = null;

        $this->attachmentManager->reset();
        // It's good practice to check if $templateProcessor is set, 
        // though in current flow it always should be by the time resetState is called.
        if (isset($this->templateProcessor)) { 
            $this->templateProcessor->reset();
        }
        
        $this->mailerName = config('mail.default');
        
        // Reset scheduling properties
        $this->scheduledAt = null;
        $this->expiresAt = null;
        $this->frequency = null;
        $this->frequencyOptions = [];
        $this->conditions = [];
        $this->retryAttempts = 0;
    }
}