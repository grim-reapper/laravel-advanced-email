<?php

namespace GrimReapper\AdvancedEmail;

use Illuminate\Support\Facades\Mail;

class EmailBuilder
{
    protected $app;
    protected $to = [];
    protected $subject;
    protected $html;
    protected $attachments = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function to($address)
    {
        $this->to[] = $address;
        return $this;
    }

    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function html($html)
    {
        $this->html = $html;
        return $this;
    }

    public function attach($file)
    {
        $this->attachments[] = $file;
        return $this;
    }

    public function send()
    {
        Mail::html($this->html, function($message) {
            $message->to($this->to)
                    ->subject($this->subject);

            foreach ($this->attachments as $attachment) {
                $message->attach($attachment);
            }
        });
    }
}