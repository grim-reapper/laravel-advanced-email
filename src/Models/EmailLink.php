<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use GrimReapper\AdvancedEmail\Models\EmailLog;

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
    public function emailLog(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}