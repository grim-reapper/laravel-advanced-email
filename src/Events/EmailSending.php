<?php

namespace GrimReapper\AdvancedEmail\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailSending
{
    use Dispatchable, SerializesModels;

    /**
     * The email data before sending.
     *
     * @var array
     */
    public array $logData;

    /**
     * Create a new event instance.
     *
     * @param array $logData
     * @return void
     */
    public function __construct(array $logData)
    {
        $this->logData = $logData;
    }
}