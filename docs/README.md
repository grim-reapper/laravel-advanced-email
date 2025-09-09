# Laravel Advanced Email Package Documentation

## Table of Contents
- [Introduction](#introduction)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Email Templates](#email-templates)
- [Advanced Features](#advanced-features)
  - [Multi-Provider Support](#multi-provider-support)
  - [Email Scheduling](#email-scheduling)
  - [Recurring Emails](#recurring-emails)
  - [Email Tracking](#email-tracking)
  - [Email Analytics](#email-analytics)
- [Events and Logging](#events-and-logging)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Introduction
Laravel Advanced Email is a powerful package that enhances Laravel's email capabilities with advanced features like template management, multi-provider support, scheduling, tracking, analytics, and comprehensive logging.

## Installation

### Requirements
- PHP 8.0 or higher
- Laravel 9.0 or higher

### Via Composer
```bash
composer require grim-reapper/laravel-advanced-email
```

### Service Provider Registration
The package will automatically register its service provider in Laravel.

### Publishing Configuration
```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="config"
```

### Publishing Views
```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="views"
```

### Database Migrations
Run the migrations to create the necessary database tables:

```bash
php artisan migrate
```

This will create the following tables:
- `email_templates` - Stores email template metadata
- `email_template_versions` - Stores versioned content for email templates
- `email_logs` - Logs all sent emails
- `email_links` - Tracks links in emails for click analytics
- `scheduled_emails` - Stores emails scheduled for future delivery

## Configuration

### Basic Configuration
After publishing, configure the package in `config/advanced_email.php`:

```php
return [
    'default_mailer' => null, // Uses Laravel's default mailer if null
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    'default_queue_connection' => null, // Uses default queue connection if null
    'default_queue_name' => null, // Uses default queue name if null
    
    'logging' => [
        'enabled' => env('ADVANCED_EMAIL_LOGGING_ENABLED', true),
        'driver' => env('ADVANCED_EMAIL_LOGGING_DRIVER', 'database'),
        
        // Database driver configuration
        'database' => [
            'model' => env('ADVANCED_EMAIL_LOG_MODEL', \GrimReapper\AdvancedEmail\Models\EmailLog::class),
            'connection' => env('ADVANCED_EMAIL_LOG_CONNECTION', null),
        ],
        
        // Log channel configuration
        'log' => [
            'channel' => env('ADVANCED_EMAIL_LOG_CHANNEL', null),
        ],
    ],
    
    // Multi-provider configuration
    'providers' => [
        // List of mail providers to use, in order of preference
        'smtp', // Default Laravel mailer
        'secondary_smtp', // Additional configured mailers
    ],
    'failover_strategy' => 'sequential', // Currently only sequential is supported
    
    // Scheduling configuration
    'scheduling' => [
        'frequency' => 'everyMinute', // How often to check for scheduled emails
        'batch_size' => 50, // Number of emails to process in each batch
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'delay' => 300, // 5 minutes in seconds
        ],
    ],
    
    // Database configuration
    'database' => [
        'connection' => null, // Database connection to use (null = default)
        'tables' => [
            'email_templates' => 'email_templates',
            'email_template_versions' => 'email_template_versions',
            'email_logs' => 'email_logs',
            'email_links' => 'email_links',
            'scheduled_emails' => 'scheduled_emails',
        ],
    ],
];
```

## Basic Usage

### Sending Basic Emails
```php
use GrimReapper\AdvancedEmail\Facades\Email;

Email::to('recipient@example.com')
    ->subject('Welcome')
    ->html('<h1>Welcome to our platform!</h1>')
    ->send();
```

### Multiple Recipients
```php
Email::to(['user1@example.com', 'user2@example.com'])
    ->cc('manager@example.com')
    ->bcc(['admin1@example.com' => 'Admin 1', 'admin2@example.com'])
    ->subject('Team Update')
    ->html('<p>Important team update</p>')
    ->send();
```

### Using Blade Templates
```php
Email::to('user@example.com')
    ->subject('Welcome')
    ->view('emails.welcome', ['name' => 'John'])
    ->send();
```

### Custom Headers

You can add custom headers to your emails for specialized use cases like email categorization, tracking, or provider-specific headers.

#### Basic Headers
```php
Email::to('recipient@example.com')
     ->subject('Email with Custom Headers')
     ->html('<p>This email includes custom headers.</p>')
     ->headers([
         'X-Custom-ID' => '123456',
         'X-Email-Type' => 'newsletter',
         'X-Campaign-ID' => 'summer-promo-2024'
     ])
     ->send();
```

#### Provider-Specific Headers
```php
// Mailgun-specific headers
Email::to('recipient@example.com')
     ->subject('Tagged Email')
     ->html('<p>This email includes Mailgun tags.</p>')
     ->headers([
         'X-Mailgun-Tag' => 'newsletter',
         'List-Unsubscribe' => '<mailto:unsubscribe@example.com>'
     ])
     ->send();

// Amazon SES tags
Email::to('recipient@example.com')
     ->subject('Campaign Email')
     ->html('<p>This is a campaign email.</p>')
     ->headers([
         'X-SES-MESSAGE-TAGS' => 'campaign=summer,audience=new-users'
     ])
     ->send();
```

#### Tracking and Analytics Headers
```php
Email::to('recipient@example.com')
     ->subject('Tracked Email')
     ->html('<p>This email is tracked with custom headers.</p>')
     ->headers([
         'X-User-ID' => auth()->id(),
         'X-Source' => 'web-registration',
         'X-Ab-Test-Variant' => 'A'
     ])
     ->send();
```

**Notes:**
- Custom headers are included in sent emails unless filtered by your email provider
- Headers work with both immediate sending and scheduled emails
- The headers are preserved when scheduling emails for future delivery

### Adding Attachments
```php
// Attach from file path
Email::to('recipient@example.com')
    ->subject('Monthly Report')
    ->view('emails.report')
    ->attach('/path/to/report.pdf', ['as' => 'monthly-report.pdf'])
    ->send();

// Attach from raw data
Email::to('recipient@example.com')
    ->subject('Generated Certificate')
    ->view('emails.certificate')
    ->attachData($pdfContent, 'certificate.pdf', ['mime' => 'application/pdf'])
    ->send();

// Attach from Laravel Storage
Email::to('recipient@example.com')
    ->subject('Your Invoice')
    ->view('emails.invoice')
    ->attachFromStorage('invoices', 'invoice-123.pdf', 'invoice.pdf')
    ->send();
```

### Queueing Emails
```php
Email::to('recipient@example.com')
    ->subject('Welcome Aboard')
    ->view('emails.welcome')
    ->queue(); // Uses default queue

// Specify queue connection and name
Email::to('recipient@example.com')
    ->subject('Welcome Aboard')
    ->view('emails.welcome')
    ->queue('redis', 'emails');
```

### Using a Specific Mailer
```php
Email::mailer('postmark')
    ->to('recipient@example.com')
    ->subject('Sent via Postmark')
    ->html('<p>This email was sent using Postmark.</p>')
    ->send();
```

## Email Templates

The package provides a database-driven template system with versioning support.

### Creating a Template

```php
use GrimReapper\AdvancedEmail\Models\EmailTemplate;
use GrimReapper\AdvancedEmail\Models\EmailTemplateVersion;

// Create the template
$template = EmailTemplate::create([
    'name' => 'welcome_email',
    'description' => 'Email sent to new users after registration',
]);

// Create the first version
$version = EmailTemplateVersion::create([
    'email_template_id' => $template->id,
    'version' => 1,
    'subject' => 'Welcome to Our Platform',
    'html_content' => '<h1>Welcome, {{name}}!</h1><p>Thank you for joining our platform.</p>',
    'text_content' => 'Welcome, {{name}}! Thank you for joining our platform.',
    'placeholders' => ['name'],
    'is_active' => true,
]);
```

### Using a Template

```php
Email::to('new-user@example.com')
    ->template('welcome_email') // Use the template name
    ->with([ // Provide values for placeholders
        'name' => 'John Doe',
    ])
    ->send();
```

### Creating a New Template Version

```php
$template = EmailTemplate::where('name', 'welcome_email')->first();

// Deactivate current active version
$template->activeVersion()->update(['is_active' => false]);

// Create new version
$newVersion = EmailTemplateVersion::create([
    'email_template_id' => $template->id,
    'version' => $template->versions()->max('version') + 1,
    'subject' => 'Welcome to Our Improved Platform',
    'html_content' => '<h1>Welcome, {{name}}!</h1><p>Thank you for joining our platform. {{custom_message}}</p>',
    'text_content' => 'Welcome, {{name}}! Thank you for joining our platform. {{custom_message}}',
    'placeholders' => ['name', 'custom_message'],
    'is_active' => true,
]);
```

## Advanced Features

### Multi-Provider Support

The package supports sending emails through multiple providers with automatic failover.

#### Configuration

In your `config/mail.php`, define multiple mailers:

```php
'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        // Primary SMTP configuration
    ],
    'postmark' => [
        'transport' => 'postmark',
        // Postmark configuration
    ],
    'ses' => [
        'transport' => 'ses',
        // Amazon SES configuration
    ],
],
```

Then in `config/advanced_email.php`, specify the provider order:

```php
'providers' => ['smtp', 'postmark', 'ses'],
'failover_strategy' => 'sequential',
```

#### Usage

The package will automatically try each provider in sequence if previous ones fail:

```php
Email::to('recipient@example.com')
    ->subject('Important Message')
    ->html('<p>This will try multiple providers if needed.</p>')
    ->send();
```

### Email Scheduling

The package allows scheduling emails for future delivery.

#### Basic Scheduling

```php
use Carbon\Carbon;

Email::to('recipient@example.com')
    ->subject('Scheduled Email')
    ->html('<p>This email was scheduled in advance.</p>')
    ->schedule(Carbon::tomorrow()) // Schedule for tomorrow
    ->saveScheduled(); // Save to database
```

#### Scheduling with Expiration

```php
Email::to('recipient@example.com')
    ->subject('Limited Time Offer')
    ->view('emails.offer')
    ->schedule(Carbon::now()->addHours(2)) // Send in 2 hours
    ->expires(Carbon::now()->addDays(1)) // Expire after 1 day
    ->saveScheduled();
```

#### Conditional Scheduling

```php
Email::to('recipient@example.com')
    ->subject('Weather Alert')
    ->view('emails.weather-alert')
    ->schedule(Carbon::tomorrow()->setHour(8)) // Tomorrow at 8 AM
    ->when(function() {
        // Only send if the forecast shows rain
        return WeatherService::getForecast() === 'rain';
    })
    ->saveScheduled();
```

### Recurring Emails

The package supports recurring emails with various frequency options.

#### Daily Recurring Email

```php
Email::to('recipient@example.com')
    ->subject('Daily Report')
    ->view('emails.daily-report')
    ->schedule(Carbon::tomorrow()->setHour(9)) // Start tomorrow at 9 AM
    ->recurring('daily') // Repeat daily
    ->saveScheduled();
```

#### Weekly Recurring Email

```php
Email::to('team@example.com')
    ->subject('Weekly Team Update')
    ->view('emails.weekly-update')
    ->schedule(Carbon::next(Carbon::MONDAY)->setHour(10)) // Next Monday at 10 AM
    ->recurring('weekly', ['days' => [Carbon::MONDAY]]) // Every Monday
    ->saveScheduled();
```

#### Monthly Recurring Email

```php
Email::to('subscriber@example.com')
    ->subject('Monthly Newsletter')
    ->template('monthly_newsletter')
    ->schedule(Carbon::now()->firstOfMonth()->addDay()) // 2nd day of current month
    ->recurring('monthly', ['day' => 2]) // 2nd day of each month
    ->saveScheduled();
```

### Email Tracking

The package provides built-in tracking for email opens and link clicks.

#### Tracking Opens

Open tracking is automatically enabled. The package adds a tiny transparent tracking pixel to emails:

```php
Email::to('recipient@example.com')
    ->subject('Trackable Email')
    ->html('<p>This email will track when it is opened.</p>')
    ->send();
```

#### Tracking Links

Link tracking is also automatic. The package rewrites links to pass through a tracking endpoint:

```php
Email::to('recipient@example.com')
    ->subject('Email with Tracked Links')
    ->html('<p>Check out our <a href="https://example.com/product">latest product</a>!</p>')
    ->send();
```

#### Retrieving Tracking Data

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;

// Get all opened emails
$openedEmails = EmailLog::opened()->get();

// Get click data for a specific email
$email = EmailLog::where('uuid', $uuid)->first();
$clickedLinks = $email->links()->where('click_count', '>', 0)->get();
```

### Email Analytics

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;

// Total emails sent
$totalSent = EmailLog::where('status', 'sent')->count();

// Total emails opened
$totalOpened = EmailLog::whereNotNull('opened_at')->count();

// Open rate
$openRate = ($totalSent > 0) ? ($totalOpened / $totalSent) * 100 : 0;

// Emails to a specific recipient
$recipientEmails = EmailLog::toAddress('specific@example.com')->get();

// Emails with a specific subject
$subjectEmails = EmailLog::withSubject('Newsletter')->get();
```

## Events and Logging

The package dispatches events during the email lifecycle:

- `EmailSending`: Before an email is sent
- `EmailSent`: After an email is successfully sent
- `EmailFailed`: When an email fails to send

You can listen for these events to perform custom actions:

```php
Event::listen(function (EmailSent $event) {
    // Custom logic when an email is sent
    $logData = $event->logData;
    // Do something with the log data
});
```

Email logging is enabled by default and can be configured in `config/advanced_email.php`.

## Testing

The package includes testing utilities to help you test email sending in your application:

```bash
php artisan test
```

## Troubleshooting

### Common Issues

#### Emails Not Being Sent

1. Check your mail configuration in `.env` and `config/mail.php`
2. Verify queue worker is running if using queued emails
3. Check the Laravel log files for errors

#### Scheduled Emails Not Processing

1. Make sure Laravel's scheduler is running: `php artisan schedule:run`
2. Check the `scheduled_emails` table for status and errors
3. Verify the `ProcessScheduledEmailsJob` is being dispatched

#### Template Placeholders Not Working

1. Ensure placeholders in the template match the data provided
2. Check that placeholders are properly formatted (e.g., `{{placeholder}}`)
3. Verify the template version is active

#### Tracking Not Working

1. Make sure the tracking routes are accessible
2. Check that emails are being sent as HTML (tracking doesn't work with plain text)
3. Verify the tracking pixel is not being blocked by email clients

### Getting Help

If you encounter issues not covered in this documentation:

1. Check the [GitHub repository](https://github.com/grim-reapper/laravel-advanced-email) for open issues
2. Submit a new issue with detailed reproduction steps
3. Reach out to the community on Laravel forums or Stack Overflow