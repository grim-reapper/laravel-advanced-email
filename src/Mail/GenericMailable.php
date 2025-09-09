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
    public array $dynamicHeaders = [];

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
     * Set custom headers for this mailable.
     *
     * @param array $headers
     * @return $this
     */
    public function setCustomHeaders(array $headers)
    {
        $this->dynamicHeaders = $headers;
        return $this;
    }

    /**
     * Callback executed after the message is created.
     * This supports both Swift (Laravel <9) and Symfony (Laravel 9+) messages.
     *
     * @param mixed $message \Swift_Message or \Symfony\Component\Mime\Email
     * @return void
     */
    protected function afterMake($message)
    {
        parent::afterMake($message);

        // Add custom headers AFTER message creation
        if (!empty($this->dynamicHeaders)) {
            \Log::info("Adding custom headers via afterMake callback", $this->dynamicHeaders);

            foreach ($this->dynamicHeaders as $key => $value) {
                try {
                    // Check if this is a Symfony message (Laravel 9+)
                    if ($message instanceof \Symfony\Component\Mime\Email) {
                        $message->getHeaders()->addTextHeader($key, $value);
                        \Log::info("Symfony after-make header added: {$key} = {$value}");

                        // Verify header was added
                        $headers = $message->getHeaders();
                        if ($headers->has($key)) {
                            $headerValue = $headers->get($key)->getBodyAsString();
                            \Log::info("Symfony after-make verified header: {$key} = {$headerValue}");
                        }
                    }
                    // Check if this is a Swift message (Laravel <9)
                    elseif ($message instanceof \Swift_Message) {
                        $message->getHeaders()->addTextHeader($key, $value);
                        \Log::info("Swift after-make header added: {$key} = {$value}");

                        // Verify header was added
                        $headers = $message->getHeaders();
                        if ($headers->has($key)) {
                            $headerValue = $headers->get($key)->getFieldBody();
                            \Log::info("Swift after-make verified header: {$key} = {$headerValue}");
                        }
                    } else {
                        \Log::warning("Unknown message type for headers: " . get_class($message));
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to add header {$key}: " . $e->getMessage());
                }
            }
        }
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
     * We override it to handle content and add custom headers.
     *
     * @return $this
     */
    public function build(): static
    {
        // Handle view/HTML content (original functionality)
        if (!empty($this->view) && is_string($this->view)) {
            $this->view($this->view, $this->dynamicViewData);
        } elseif (!empty($this->dynamicHtmlContent)) {
            $html = is_string($this->dynamicHtmlContent)
                ? $this->dynamicHtmlContent
                : $this->dynamicHtmlContent->render();
            $this->html($html);
        }

        // Add custom headers using Symfony Mailer (Laravel 9+)
        if (!empty($this->dynamicHeaders)) {
            $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) {
                foreach ($this->dynamicHeaders as $key => $value) {
                    $email->getHeaders()->addTextHeader($key, $value);
                }
            });
        }

        return $this;
    }
}