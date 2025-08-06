<?php

namespace GrimReapper\AdvancedEmail\Services;

use GrimReapper\AdvancedEmail\Models\EmailTemplate;
use GrimReapper\AdvancedEmail\Models\EmailTemplateVersion; // Assuming this is the correct model
use Illuminate\Support\Facades\Log;

class ProcessedTemplateData
{
    public function __construct(
        public ?string $subject,
        public ?string $htmlBody,
        public bool $isFromDatabase,
        public ?string $loadedTemplateName, // To store the name of the template that was actually loaded
        public ?array $emailConfig = null // Email configuration from template version
    ) {}
}

class TemplateProcessor
{
    protected ?string $templateName = null;
    protected array $placeholders = [];
    protected array $placeholderPatterns = [];
    protected bool $isContentFromDatabaseTemplate = false; // Internal flag based on what was loaded

    // Properties to hold original subject/html if not using a DB template
    protected ?string $directSubject = null;
    protected ?string $directHtmlContent = null;
    
    // Property to store the loaded template version for email configuration access
    protected ?EmailTemplateVersion $loadedTemplateVersion = null;

    public function __construct()
    {
        $this->registerDefaultPlaceholderPatterns();
    }

    public function setTemplateName(?string $name): static
    {
        $this->templateName = $name;
        // When a DB template is set, direct html/subject are not primary sources
        $this->directHtmlContent = null; 
        $this->directSubject = null;
        $this->isContentFromDatabaseTemplate = (bool)$name; // True if a template name is provided
        return $this;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }
    
    public function setDirectHtmlContent(?string $html): static
    {
        $this->directHtmlContent = $html;
        // Only override template if HTML content is actually provided
        if ($html !== null) {
            $this->templateName = null; // Direct HTML overrides DB template
            $this->isContentFromDatabaseTemplate = false;
        }
        return $this;
    }

    public function setDirectSubject(?string $subject): static
    {
        $this->directSubject = $subject;
        // Setting subject directly doesn't necessarily mean we aren't using a DB template for body
        // This might need refinement based on how EmailService uses it.
        // For now, assume direct subject can override template subject.
        return $this;
    }

    public function addPlaceholders(array $placeholders): static
    {
        $this->placeholders = array_merge($this->placeholders, $placeholders);
        return $this;
    }
    
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    /**
     * Get email configuration from the loaded template version.
     * 
     * Returns the email configuration from the currently loaded template version,
     * or null if no template is loaded or no email configuration exists.
     * 
     * @return array|null Email configuration array or null if not available
     */
    public function getEmailConfiguration(): ?array
    {
        if (!$this->loadedTemplateVersion) {
            return null;
        }

        return $this->loadedTemplateVersion->getEmailConfiguration();
    }

    /**
     * Parse email list from various formats (string, array, or null).
     *
     * @param mixed $emailData
     * @return array
     */
    protected function parseEmailList($emailData): array
    {
        if (empty($emailData)) {
            return [];
        }

        if (is_array($emailData)) {
            return array_filter(array_map('trim', $emailData));
        }

        if (is_string($emailData)) {
            // Handle comma-separated string format
            return array_filter(array_map('trim', explode(',', $emailData)));
        }

        return [];
    }

    /**
     * Get the loaded template version instance.
     *
     * @return EmailTemplateVersion|null
     */
    public function getLoadedTemplateVersion(): ?EmailTemplateVersion
    {
        return $this->loadedTemplateVersion;
    }

    /**
     * Validate and get email configuration from template version.
     * 
     * Extracts email configuration from the template version and validates it.
     * Invalid email addresses are logged and excluded from the result.
     * Returns sanitized configuration with only valid email addresses.
     * 
     * @param EmailTemplateVersion $templateVersion The template version to extract config from
     * @return array|null Validated email configuration or null if invalid/empty
     */
    protected function validateAndGetEmailConfiguration(EmailTemplateVersion $templateVersion): ?array
    {
        try {
            $emailConfig = $templateVersion->getEmailConfiguration();
            
            if (!$emailConfig) {
                return null;
            }

            // Validate email configuration using the model's validation
            $validator = EmailTemplateVersion::validateEmailConfig($emailConfig);
            
            if ($validator->fails()) {
                Log::warning("Invalid email configuration in template version", [
                    'template_name' => $this->templateName,
                    'template_version_id' => $templateVersion->id,
                    'validation_errors' => $validator->errors()->toArray()
                ]);
                
                // Return sanitized configuration with only valid fields
                return $this->sanitizeEmailConfiguration($emailConfig);
            }

            return $emailConfig;
            
        } catch (\Exception $e) {
            Log::error("Error validating email configuration for template", [
                'template_name' => $this->templateName,
                'template_version_id' => $templateVersion->id ?? null,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Sanitize email configuration by removing invalid entries.
     *
     * @param array $emailConfig
     * @return array
     */
    protected function sanitizeEmailConfiguration(array $emailConfig): array
    {
        $sanitized = [];

        // Validate and sanitize individual email addresses
        if (!empty($emailConfig['from_email']) && filter_var($emailConfig['from_email'], FILTER_VALIDATE_EMAIL)) {
            $sanitized['from_email'] = $emailConfig['from_email'];
        }

        if (!empty($emailConfig['from_name']) && is_string($emailConfig['from_name'])) {
            $sanitized['from_name'] = $emailConfig['from_name'];
        }

        if (!empty($emailConfig['reply_to_email']) && filter_var($emailConfig['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
            $sanitized['reply_to_email'] = $emailConfig['reply_to_email'];
        }

        if (!empty($emailConfig['reply_to_name']) && is_string($emailConfig['reply_to_name'])) {
            $sanitized['reply_to_name'] = $emailConfig['reply_to_name'];
        }

        // Sanitize email arrays
        foreach (['to_email', 'cc_email', 'bcc_email'] as $field) {
            if (!empty($emailConfig[$field]) && is_array($emailConfig[$field])) {
                $validEmails = array_filter($emailConfig[$field], function($email) {
                    return !empty($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL);
                });
                
                if (!empty($validEmails)) {
                    $sanitized[$field] = array_values($validEmails);
                }
            }
        }

        if (!empty($sanitized)) {
            Log::info("Email configuration sanitized", [
                'template_name' => $this->templateName,
                'original_fields' => array_keys($emailConfig),
                'sanitized_fields' => array_keys($sanitized)
            ]);
        }

        return $sanitized;
    }

    public function registerPattern(string $pattern, ?callable $callback = null): static
    {
        $this->placeholderPatterns[] = ['pattern' => $pattern, 'callback' => $callback];
        return $this;
    }

    protected function registerDefaultPlaceholderPatterns(): void
    {
        $this->placeholderPatterns[] = ['pattern' => '/\{\{\s*([\w.-]+)\s*\}\}/', 'callback' => null];
        $this->placeholderPatterns[] = ['pattern' => '/(?<![#\da-fA-F])##([\w.-]+)##/', 'callback' => null];
        $this->placeholderPatterns[] = ['pattern' => '/\[\[\s*([\w.-]+)\s*\]\]/', 'callback' => null];
    }

    protected function applyPlaceholders(?string $content): ?string
    {
        if ($content === null || empty($this->placeholders) || empty($this->placeholderPatterns)) {
            return $content;
        }

        foreach ($this->placeholderPatterns as $patternData) {
            $pattern = $patternData['pattern'];
            $callback = $patternData['callback'];

            if ($callback && is_callable($callback)) {
                $content = preg_replace_callback($pattern, function ($matches) use ($callback) {
                    $placeholderName = $matches[1] ?? null;
                    if ($placeholderName === null) return $matches[0];
                    return $callback($placeholderName, $this->placeholders[$placeholderName] ?? $matches[0], $this->placeholders);
                }, $content);
            } else {
                $content = preg_replace_callback($pattern, function ($matches) {
                    $placeholderName = $matches[1] ?? null;
                    if ($placeholderName === null) return $matches[0];
                    return $this->placeholders[$placeholderName] ?? $matches[0];
                }, $content);
            }
        }
        return $content;
    }

    public function loadAndProcess(): ProcessedTemplateData
    {
        $currentSubject = $this->directSubject;
        $currentHtmlBody = $this->directHtmlContent;
        // This internal flag is true if templateName is set, false if html() or view() was called last.
        // It will be updated if a DB template load attempt is made.
        $finalIsFromDb = false; 
        $loadedTemplateName = null;
        $emailConfig = null;

        // Reset the loaded template version
        $this->loadedTemplateVersion = null;

        if ($this->templateName) {
            try {
                $template = EmailTemplate::where('name', $this->templateName)->firstOrFail();
                $activeVersion = $template->activeVersion;

                if ($activeVersion) {
                    
                    // Store the loaded template version for email configuration access
                    $this->loadedTemplateVersion = $activeVersion;
                    
                    $loadedTemplateName = $this->templateName;
                    // Template subject overrides direct subject only if direct subject wasn't set AFTER template()
                    // Or, more simply, template subject is default, direct subject is override.
                    // Current logic: if directSubject is set, it wins. If not, template subject is used.
                    $currentSubject = $this->directSubject ?? $activeVersion->subject;
                    $currentHtmlBody = $activeVersion->html_content; // Template body always wins if template is set
                    
                    // Placeholders from template are defaults, those added via with() are merged (and override)
                    $this->placeholders = array_merge($activeVersion->placeholders ?? [], $this->placeholders);
                    
                    // Get email configuration from the template version with validation
                    $emailConfig = $this->validateAndGetEmailConfiguration($activeVersion);
                    
                    $finalIsFromDb = true; // Content is successfully from DB
                } else {
                    Log::warning("No active version found for email template: {$this->templateName}");
                }
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                Log::error("Email template not found: {$this->templateName}");
            } catch (\Throwable $e) {
                Log::error("Error loading email template '{$this->templateName}': " . $e->getMessage());
            }
        }
        
        $processedSubject = $this->applyPlaceholders($currentSubject);
        $processedHtmlBody = $this->applyPlaceholders($currentHtmlBody);

        return new ProcessedTemplateData($processedSubject, $processedHtmlBody, $finalIsFromDb, $loadedTemplateName, $emailConfig);
    }

    public function reset(): void
    {
        $this->templateName = null;
        $this->placeholders = [];
        // Placeholder patterns are usually registered once, so not resetting them by default.
        // $this->placeholderPatterns = []; 
        // $this->registerDefaultPlaceholderPatterns();
        $this->isContentFromDatabaseTemplate = false;
        $this->directHtmlContent = null;
        $this->directSubject = null;
        $this->loadedTemplateVersion = null;
    }
}
