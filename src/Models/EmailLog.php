<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs'; // Default table name, can be overridden by config

    protected $fillable = [
        'uuid',
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
        'sent_at',
        'opened_at',
        'status',
        'error',
    ];

    protected $casts = [
        'from' => 'array',
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'view_data' => 'array',
        'placeholders' => 'array',
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('advanced_email.database.tables.email_logs', parent::getTable());
    }

    /**
     * Get the database connection for the model.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return config('advanced_email.database.connection', parent::getConnectionName());
    }

    /**
     * Scope a query to only include opened emails.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpened(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('opened_at');
    }

    /**
     * Get the links associated with this email log.
     */
    public function links(): HasMany
    {
        return $this->hasMany(EmailLink::class);
    }

    /**
     * Scope a query to only include emails sent to a specific address.
     */
    public function scopeToAddress($query, string $email)
    {
        return $query->whereJsonContains('to', ['address' => $email])
                     ->orWhereJsonContains('to', $email); // Handle simple array of emails
    }

    /**
     * Scope a query to only include emails with a specific subject.
     */
    public function scopeWithSubject($query, string $subject)
    {
        return $query->where('subject', 'like', '%' . $subject . '%');
    }

    /**
     * Scope a query to only include emails sent within a specific date range.
     */
    public function scopeSentBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('sent_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include emails that have not been opened.
     */
    public function scopeNotOpened($query)
    {
        return $query->whereNull('opened_at');
    }

    /**
     * Scope a query to only include emails with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}