<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
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
}