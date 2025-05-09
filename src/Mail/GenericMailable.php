<?php

namespace GrimReapper\AdvancedEmail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenericMailable extends Mailable
{
    use Queueable, SerializesModels;

    public array $dynamicViewData = [];
    public ?string $dynamicHtmlContent = null;
    public array $dynamicAttachments = [];
    public array $dynamicRawAttachments = [];
    public array $dynamicStorageAttachments = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Constructor can be used for dependency injection if needed later
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        // Subject, from, to, cc, bcc are set dynamically via EmailService
        // before sending/queuing. We return an empty envelope here initially.
        return new Envelope();
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    // public function content(): Content
    // {
    //     // Return empty content as we'll handle it in build()
    //     return new Content(htmlString: ' ');
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->dynamicAttachments as $attachment) {
            $attachments[] = Attachment::fromPath($attachment['file'])
                ->as($attachment['options']['as'] ?? null)
                ->withMime($attachment['options']['mime'] ?? null);
        }

        foreach ($this->dynamicRawAttachments as $attachment) {
            $attachments[] = Attachment::fromData(fn () => $attachment['data'], $attachment['name'])
                ->withMime($attachment['options']['mime'] ?? null);
        }

        foreach ($this->dynamicStorageAttachments as $attachment) {
            $attachments[] = Attachment::fromStorageDisk($attachment['disk'], $attachment['path'])
                ->as($attachment['name'])
                ->withMime($attachment['options']['mime'] ?? null);
        }

        return $attachments;
    }

    /**
     * Build the message.
     *
     * This method is called by Laravel's mailer.
     * We override it to apply dynamic properties set by EmailService.
     *
     * @return $this
     */
    public function build(): static
    {
        // Handle view/HTML content here instead of content() method
        if (!empty($this->view) && is_string($this->view)) {
            $this->view($this->view, $this->dynamicViewData);
        } elseif (!empty($this->dynamicHtmlContent)) {
            $html = is_string($this->dynamicHtmlContent) 
                ? $this->dynamicHtmlContent 
                : $this->dynamicHtmlContent->render();
            $this->html($html);
        }

        return $this;
    }
}