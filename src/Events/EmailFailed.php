<?php

namespace GrimReapper\AdvancedEmail\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EmailFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The email data when sending failed.
     *
     * @var array
     */
    public array $logData;

    /**
     * The exception that occurred.
     *
     * @var \Throwable
     */
    public Throwable $exception;

    /**
     * Create a new event instance.
     *
     * @param array $logData
     * @param \Throwable $exception
     * @return void
     */
    public function __construct(array $logData, Throwable $exception)
    {
        $this->logData = $logData;
        $this->logData['status'] = 'failed'; // Update status
        $this->logData['error'] = $exception->getMessage(); // Ensure error message is set
        $this->exception = $exception;

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