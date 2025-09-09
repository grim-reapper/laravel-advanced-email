<?php

namespace GrimReapper\AdvancedEmail\Contracts;

interface EmailBuilder
{
    /**
     * Set the email template for the message.
     *
     * @param  string  $template
     * @return static
     */
    public function template(string $template): static;
    
    /**
     * Set the recipients of the message.
     *
     * @param  string|array<int, string>|array<string, string>  $address
     * @param  string|null  $name
     * @return static
     */
    public function to(string|array $address, ?string $name = null): static;

    /**
     * Set the CC recipients of the message.
     *
     * @param  string|array<int, string>|array<string, string>  $address
     * @param  string|null  $name
     * @return static
     */
    public function cc(string|array $address, ?string $name = null): static;

    /**
     * Set the BCC recipients of the message.
     *
     * @param  string|array<int, string>|array<string, string>  $address
     * @param  string|null  $name
     * @return static
     */
    public function bcc(string|array $address, ?string $name = null): static;

    /**
     * Set the sender of the message.
     *
     * @param  string  $address
     * @param  string|null  $name
     * @return static
     */
    public function from(string $address, ?string $name = null): static;
    
    /**
     * Set placeholder values for the email template.
     *
     * @param  array  $placeholders  Key-value pairs of placeholder values
     * @return static
     */
    public function with(array $placeholders): static;

    /**
     * Set the subject of the message.
     *
     * @param  string  $subject
     * @return static
     */
    public function subject(string $subject): static;

    /**
     * Set the HTML content of the message.
     *
     * @param  string  $htmlContent
     * @param  array<string, mixed>  $placeholders
     * @return static
     */
    public function html(string $htmlContent, array $placeholders = []): static;

    /**
     * Register a custom placeholder pattern.
     *
     * @param string $pattern Regex pattern (e.g., '/__([\w]+)__/').
     * @param callable|null $callback Optional callback for custom replacement logic.
     * @return static
     */
    public function registerPlaceholderPattern(string $pattern, ?callable $callback = null): static;

    /**
     * Set the Blade view for the message body.
     *
     * @param  string  $view
     * @param  array<string, mixed>  $data
     * @return static
     */
    public function view(string $view, array $data = []): static;

    /**
     * Attach a file to the message.
     *
     * @param  string  $file Path to the file.
     * @param  array<string, mixed>  $options
     * @return static
     */
    public function attach(string $file, array $options = []): static;

    /**
     * Attach a file to the message using raw data.
     *
     * @param  string  $data Raw file data.
     * @param  string  $name Desired file name.
     * @param  array<string, mixed>  $options
     * @return static
     */
    public function attachData(string $data, string $name, array $options = []): static;

    /**
     * Attach a file from a Laravel storage disk.
     *
     * @param  string  $disk
     * @param  string  $path
     * @param  string|null  $name
     * @param  array<string, mixed>  $options
     * @return static
     */
    public function attachFromStorage(string $disk, string $path, ?string $name = null, array $options = []): static;

    /**
     * Set custom headers for the email.
     *
     * @param  array<string, string>  $headers  Associative array of header key-value pairs
     * @return static
     */
    public function headers(array $headers): static;

    /**
     * Schedule the email to be sent at a specific time.
     *
     * @param  \DateTimeInterface|string  $dateTime
     * @return static
     */
    public function schedule(\DateTimeInterface|string $dateTime): static;

    /**
     * Set the expiration date for the scheduled email.
     *
     * @param  \DateTimeInterface|\Carbon\Carbon|string|null  $dateTime
     * @return static
     */
    public function expires(\DateTimeInterface|\Carbon\Carbon|string|null $dateTime): static;

    /**
     * Set the email to be recurring based on a frequency.
     *
     * @param  string  $mailer
     * @return static
     */
    public function mailer(string $mailer): static;

    /**
     * Send the email synchronously.
     *
     * @return void
     */
    public function send(): void;

    /**
     * Queue the email for sending.
     *
     * @param  string|null  $connection
     * @param  string|null  $queue
     * @return void
     */
    public function queue(?string $connection = null, ?string $queue = null): void;

    /**
     * Save the email as a scheduled email in the database.
     *
     * @return \GrimReapper\AdvancedEmail\Models\ScheduledEmail
     */
    public function saveScheduled(): \GrimReapper\AdvancedEmail\Models\ScheduledEmail;
}