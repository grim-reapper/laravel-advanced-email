<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class EmailTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_template_id',
        'version',
        'subject',
        'html_content',
        'text_content',
        'placeholders',
        'is_active',
        // Email configuration fields
        'from_email',
        'from_name',
        'to_email',
        'cc_email',
        'bcc_email',
        'reply_to_email',
        'reply_to_name',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
        // Cast email lists as arrays for JSON storage
        'to_email' => 'json',
        'cc_email' => 'json', 
        'bcc_email' => 'json',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        // Use the table name defined in the config, or default to 'email_template_versions'
        return config('advanced_email.database.tables.email_template_versions', 'email_template_versions');
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
     * Get the email template that owns the version.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Get validation rules for email configuration fields.
     *
     * @return array
     */
    public static function getEmailConfigValidationRules(): array
    {
        return [
            'from_email' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'to_email' => 'nullable|array',
            'to_email.*.address' => 'required|email|max:255',
            'to_email.*.name' => 'nullable|string|max:255',
            'cc_email' => 'nullable|array',
            'cc_email.*.address' => 'required|email|max:255',
            'cc_email.*.name' => 'nullable|string|max:255',
            'bcc_email' => 'nullable|array',
            'bcc_email.*.address' => 'required|email|max:255',
            'bcc_email.*.name' => 'nullable|string|max:255',
            'reply_to_email' => 'nullable|email|max:255',
            'reply_to_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Validate email configuration fields.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validateEmailConfig(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, static::getEmailConfigValidationRules());
    }

    /**
     * Parse email list from various formats into consistent object format.
     * 
     * Supports multiple input formats:
     * - JSON array of objects: [{"address": "email@example.com", "name": "Name"}]
     * - Simple array: ["email1@example.com", "email2@example.com"]
     * - Comma-separated string: "email1@example.com, email2@example.com"
     * - Mixed formats for backward compatibility
     * 
     * @param mixed $emailData The email data in various formats
     * @return array<int, array{address: string, name: string|null}> Normalized array of email objects
     */
    public function parseEmailList($emailData): array
    {
        if (empty($emailData)) {
            return [];
        }

        if (is_array($emailData)) {
            return $this->normalizeEmailArray($emailData);
        }

        if (is_string($emailData)) {
            // First try to decode as JSON (in case casting failed)
            $decoded = json_decode($emailData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeEmailArray($decoded);
            }
            
            // Handle comma-separated string format
            $emails = array_filter(array_map('trim', explode(',', $emailData)));
            return $this->normalizeEmailArray($emails);
        }

        return [];
    }

    /**
     * Normalize email array to consistent format with address and name.
     * 
     * Converts various array formats to standard object format:
     * - Primary: [{"address": "email@example.com", "name": "Name"}]
     * - Legacy key-value: ["email@example.com" => "Name"]
     * - Legacy simple: ["email@example.com"]
     * 
     * @param array $emailArray Input array in various formats
     * @return array<int, array{address: string, name: string|null}> Normalized email objects
     */
    protected function normalizeEmailArray(array $emailArray): array
    {
        $normalized = [];

        foreach ($emailArray as $key => $value) {
            if (is_array($value)) {
                // Standard object format: {"address": "email", "name": "name"}
                if (isset($value['address']) && is_string($value['address'])) {
                    $email = trim($value['address']);
                    $name = isset($value['name']) && is_string($value['name']) ? trim($value['name']) : null;
                    if (!empty($email)) {
                        $normalized[] = [
                            'address' => $email,
                            'name' => $name ?: null
                        ];
                    }
                }
            } elseif (is_string($key) && !is_numeric($key)) {
                // Key-value format: ['email@example.com' => 'Name'] (legacy support)
                $email = trim($key);
                $name = is_string($value) ? trim($value) : null;
                if (!empty($email)) {
                    $normalized[] = [
                        'address' => $email,
                        'name' => $name ?: null
                    ];
                }
            } elseif (is_string($value)) {
                // Simple array format: ['email@example.com'] (legacy support)
                $email = trim($value);
                if (!empty($email)) {
                    $normalized[] = [
                        'address' => $email,
                        'name' => null
                    ];
                }
            }
        }

        return $normalized;
    }





    /**
     * Format email list for storage (ensures consistent array format).
     *
     * @param mixed $emailData
     * @return array|null
     */
    public function formatEmailListForStorage($emailData): ?array
    {
        $parsed = $this->parseEmailList($emailData);
        return empty($parsed) ? null : $parsed;
    }

    /**
     * Get formatted sender information.
     *
     * @return array|null
     */
    public function getSenderInfo(): ?array
    {
        if (empty($this->from_email)) {
            return null;
        }

        return [
            'address' => $this->from_email,
            'name' => $this->from_name,
        ];
    }

    /**
     * Get formatted reply-to information.
     *
     * @return array|null
     */
    public function getReplyToInfo(): ?array
    {
        if (empty($this->reply_to_email)) {
            return null;
        }

        return [
            'address' => $this->reply_to_email,
            'name' => $this->reply_to_name,
        ];
    }

    /**
     * Get all email configuration as an array.
     * 
     * Returns email configuration with parsed email lists in consistent format.
     * Email lists are returned as arrays of objects with 'address' and 'name' keys.
     * 
     * @return array{
     *     from_email: string|null,
     *     from_name: string|null,
     *     to_email: array<int, array{address: string, name: string|null}>,
     *     cc_email: array<int, array{address: string, name: string|null}>,
     *     bcc_email: array<int, array{address: string, name: string|null}>,
     *     reply_to_email: string|null,
     *     reply_to_name: string|null
     * }
     */
    public function getEmailConfiguration(): array
    {
        return [
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'to_email' => $this->parseEmailList($this->to_email),
            'cc_email' => $this->parseEmailList($this->cc_email),
            'bcc_email' => $this->parseEmailList($this->bcc_email),
            'reply_to_email' => $this->reply_to_email,
            'reply_to_name' => $this->reply_to_name,
        ];
    }

    /**
     * Check if the template version has any email configuration.
     *
     * @return bool
     */
    public function hasEmailConfiguration(): bool
    {
        return !empty($this->from_email) ||
               !empty($this->from_name) ||
               !empty($this->to_email) ||
               !empty($this->cc_email) ||
               !empty($this->bcc_email) ||
               !empty($this->reply_to_email) ||
               !empty($this->reply_to_name);
    }

    /**
     * Mutator for from_email field with validation.
     *
     * @param mixed $value
     * @return void
     */
    public function setFromEmailAttribute($value): void
    {
        if ($value && !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            \Illuminate\Support\Facades\Log::warning("Invalid from_email provided to EmailTemplateVersion", [
                'email' => $value,
                'template_version_id' => $this->id ?? 'new'
            ]);
            $this->attributes['from_email'] = null;
        } else {
            $this->attributes['from_email'] = $value ? trim($value) : null;
        }
    }

    /**
     * Mutator for reply_to_email field with validation.
     *
     * @param mixed $value
     * @return void
     */
    public function setReplyToEmailAttribute($value): void
    {
        if ($value && !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            \Illuminate\Support\Facades\Log::warning("Invalid reply_to_email provided to EmailTemplateVersion", [
                'email' => $value,
                'template_version_id' => $this->id ?? 'new'
            ]);
            $this->attributes['reply_to_email'] = null;
        } else {
            $this->attributes['reply_to_email'] = $value ? trim($value) : null;
        }
    }

    /**
     * Mutator for to_email field to ensure consistent array storage with validation.
     *
     * @param mixed $value
     * @return void
     */
    public function setToEmailAttribute($value): void
    {
        $validatedEmails = $this->validateAndFormatEmailListForStorage($value, 'to_email');
        $this->attributes['to_email'] = $validatedEmails ? json_encode($validatedEmails) : null;
    }

    /**
     * Mutator for cc_email field to ensure consistent array storage with validation.
     *
     * @param mixed $value
     * @return void
     */
    public function setCcEmailAttribute($value): void
    {
        $validatedEmails = $this->validateAndFormatEmailListForStorage($value, 'cc_email');
        $this->attributes['cc_email'] = $validatedEmails ? json_encode($validatedEmails) : null;
    }

    /**
     * Mutator for bcc_email field to ensure consistent array storage with validation.
     *
     * @param mixed $value
     * @return void
     */
    public function setBccEmailAttribute($value): void
    {
        $validatedEmails = $this->validateAndFormatEmailListForStorage($value, 'bcc_email');
        $this->attributes['bcc_email'] = $validatedEmails ? json_encode($validatedEmails) : null;
    }

    /**
     * Validate and format email list for storage with logging of invalid emails.
     * Now handles objects with address and name.
     *
     * @param mixed $emailData
     * @param string $fieldName
     * @return array|null
     */
    protected function validateAndFormatEmailListForStorage($emailData, string $fieldName): ?array
    {
        $parsed = $this->parseEmailList($emailData);
        
        if (empty($parsed)) {
            return null;
        }

        $validEmails = [];
        $invalidEmails = [];

        foreach ($parsed as $emailObj) {
            $email = $emailObj['address'];
            $name = $emailObj['name'];
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = [
                    'address' => $email,
                    'name' => $name
                ];
            } else {
                $invalidEmails[] = $email;
            }
        }

        if (!empty($invalidEmails)) {
            \Illuminate\Support\Facades\Log::warning("Invalid emails found in EmailTemplateVersion {$fieldName}", [
                'field' => $fieldName,
                'template_version_id' => $this->id ?? 'new',
                'invalid_emails' => $invalidEmails,
                'valid_emails_count' => count($validEmails)
            ]);
        }

        return empty($validEmails) ? null : $validEmails;
    }
}