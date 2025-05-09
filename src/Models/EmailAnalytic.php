<?php

namespace GrimReapper\AdvancedEmail\Models;

use Illuminate\Database\Eloquent\Model;

class EmailAnalytic extends Model
{
    protected $fillable = [
        'message_id',
        'recipient_email',
        'subject',
        'template_id',
        'sent_at',
        'opened_at',
        'click_count',
        'click_data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'click_data' => 'array',
    ];
}