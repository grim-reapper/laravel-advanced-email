<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        // Use the table name defined in the config, or default to 'email_templates'
        return config('advanced_email.database.tables.email_templates', 'email_templates');
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
     * Get all versions associated with the email template.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class);
    }

    /**
     * Get the currently active version for the email template.
     */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(EmailTemplateVersion::class)->where('is_active', true);
    }
}