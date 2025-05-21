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
        $this->logData['status'] = 'sending'; // Update status

        // If 'from' is not set, get it from the mail configuration
        if (empty($this->logData['from'])) {
            $defaultFrom = config('mail.from', ['address' => null, 'name' => null]);
            $this->logData['from'] = [
                'address' => $defaultFrom['address'] ?? null,
                'name' => $defaultFrom['name'] ?? null
            ];
        }
    }
}