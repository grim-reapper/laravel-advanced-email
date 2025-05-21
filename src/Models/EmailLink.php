<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLink extends Model
{
    use HasFactory;

    protected $table = 'email_links';

    protected $fillable = [
        'uuid',
        'email_log_id',
        'original_url',
        'click_count',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'click_count' => 'integer',
    ];

    /**
     * Get the email log that owns the link.
     */
    public function emailLog()
    {
        // Assuming you have an EmailLog model, adjust namespace if needed
        // return $this->belongsTo(EmailLog::class);
        // If EmailLog model doesn't exist yet or is named differently, comment out or adjust
        return null; // Placeholder
    }
}