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
        public ?string $loadedTemplateName // To store the name of the template that was actually loaded
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
        $this->templateName = null; // Direct HTML overrides DB template
        $this->isContentFromDatabaseTemplate = false;
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

        if ($this->templateName) {
            try {
                $template = EmailTemplate::where('name', $this->templateName)->firstOrFail();
                $activeVersion = $template->activeVersion;

                if ($activeVersion) {
                    $loadedTemplateName = $this->templateName;
                    // Template subject overrides direct subject only if direct subject wasn't set AFTER template()
                    // Or, more simply, template subject is default, direct subject is override.
                    // Current logic: if directSubject is set, it wins. If not, template subject is used.
                    $currentSubject = $this->directSubject ?? $activeVersion->subject;
                    $currentHtmlBody = $activeVersion->html_content; // Template body always wins if template is set

                    // Placeholders from template are defaults, those added via with() are merged (and override)
                    $this->placeholders = array_merge($activeVersion->placeholders ?? [], $this->placeholders);
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

        return new ProcessedTemplateData($processedSubject, $processedHtmlBody, $finalIsFromDb, $loadedTemplateName);
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
    }
}
