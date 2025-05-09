# Laravel Advanced Email Package Documentation

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Basic Usage](#basic-usage)
5. [Email Templates](#email-templates)
6. [Advanced Features](#advanced-features)
   - [Multi-Provider Support](#multi-provider-support)
   - [Email Scheduling](#email-scheduling)
   - [Recurring Emails](#recurring-emails)
   - [Email Tracking](#email-tracking)
   - [Email Analytics](#email-analytics)
7. [API Reference](#api-reference)
8. [Troubleshooting](#troubleshooting)

## Introduction

The Laravel Advanced Email package provides a robust and flexible way to handle email sending within Laravel applications. It extends Laravel's native mail functionality with advanced features like email templating, scheduling, tracking, analytics, and multi-provider support.

Key features include:

- **Fluent Interface**: Chainable methods for building email messages
- **Template Management**: Database-driven email templates with versioning
- **Advanced Scheduling**: Schedule emails with precise timing and recurrence
- **Multi-Provider Support**: Failover between multiple email providers
- **Email Tracking**: Track email opens and link clicks
- **Analytics**: Comprehensive email analytics and reporting
- **Attachment Handling**: Attach files from paths, raw data, or Laravel Storage disks

## Installation

### Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher

### Composer Installation

You can install the package via Composer:

```bash
composer require grim-reapper/laravel-advanced-email
```

The package will automatically register its service provider.

### Publishing Assets

Publish the configuration file:

```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="config"
```

Optionally, publish the example view file:

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

After publishing the configuration file (`config/advanced_email.php`), you can customize various aspects of the package:

### Default Mailer

```php
'default_mailer' => null, // Uses Laravel's default mailer if null
```

### Default From Address

```php
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```

### Queue Configuration

```php
'default_queue_connection' => null, // Uses default queue connection if null
'default_queue_name' => null, // Uses default queue name if null
```

### Email Logging

```php
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
```

### Multi-Provider Configuration

```php
'providers' => [
    // List of mail providers to use, in order of preference
    'smtp', // Default Laravel mailer
    'secondary_smtp', // Additional configured mailers
],

'failover_strategy' => 'sequential', // Currently only sequential is supported
```

### Scheduling Configuration

```php
'scheduling' => [
    'frequency' => 'everyMinute', // How often to check for scheduled emails
    'batch_size' => 50, // Number of emails to process in each batch
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 300, // 5 minutes in seconds
    ],
],
```

### Database Configuration

```php
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
```

## Basic Usage

### Sending a Simple Email

```php
use GrimReapper\AdvancedEmail\Facades\Email;

Email::to('recipient@example.com')
    ->subject('Hello World')
    ->html('<h1>Hello World!</h1><p>This is a test email.</p>')
    ->send();
```

### Using a Blade View

```php
Email::to('recipient@example.com')
    ->subject('Welcome to Our Service')
    ->view('emails.welcome', [
        'name' => 'John Doe',
        'activationLink' => 'https://example.com/activate/123',
    ])
    ->send();
```

### Adding CC and BCC Recipients

```php
Email::to('primary@example.com')
    ->cc(['cc1@example.com', 'cc2@example.com' => 'CC Recipient'])
    ->bcc('bcc@example.com')
    ->subject('Team Update')
    ->view('emails.team-update', ['update' => 'New feature released!'])
    ->send();
```

### Customizing the From Address

```php
Email::to('recipient@example.com')
    ->from('noreply@yourcompany.com', 'Your Company Name')
    ->subject('Important Notification')
    ->html('<p>This is an important notification.</p>')
    ->send();
```

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
    ->attachFromStorage('local', 'invoice-123.pdf', 'invoice.pdf')
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

Or specify a particular provider:

```php
Email::mailer('ses')
    ->to('recipient@example.com')
    ->subject('Via Amazon SES')
    ->html('<p>This will only use Amazon SES.</p>')
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

The package supports various types of conditions for scheduled emails using the `when()` method. Conditions are evaluated before sending the scheduled email.

```php
// Event-based condition
Email::to('recipient@example.com')
    ->subject('Order Confirmation')
    ->view('emails.order-confirmation')
    ->schedule(Carbon::now()->addMinutes(30))
    ->when('event', [
        'name' => 'order.confirmed',
        'data' => ['order_id' => 123]
    ])
    ->saveScheduled();

// Database condition
Email::to('user@example.com')
    ->subject('Subscription Reminder')
    ->view('emails.subscription-reminder')
    ->schedule(Carbon::tomorrow()->setHour(9))
    ->when('database', [
        'table' => 'subscriptions',
        'where' => ['user_id' => 1, 'status' => 'active']
    ])
    ->saveScheduled();

// Time-based condition
Email::to('customer@example.com')
    ->subject('Special Offer')
    ->view('emails.special-offer')
    ->schedule(Carbon::now()->addDay())
    ->when('time', [
        'between' => [
            'start' => '09:00',
            'end' => '17:00'
        ],
        'days_of_week' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
    ])
    ->saveScheduled();

// Callback condition
Email::to('admin@example.com')
    ->subject('System Health Report')
    ->view('emails.system-health')
    ->schedule(Carbon::now()->addHours(1))
    ->when('callback', function($scheduledEmail) {
        // Custom logic to determine if email should be sent
        return SystemHealth::check()->hasIssues();
    })
    ->saveScheduled();
```

#### Retry Configuration

```php
Email::to('recipient@example.com')
    ->subject('Important System Notification')
    ->html('<p>This is an important system notification.</p>')
    ->schedule(Carbon::now()->addMinutes(30))
    ->retryAttempts(5) // Retry up to 5 times if sending fails
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

#### Custom Recurring Email

```php
Email::to('recipient@example.com')
    ->subject('Bi-weekly Reminder')
    ->html('<p>This is your bi-weekly reminder.</p>')
    ->schedule(Carbon::now()->addDays(1))
    ->recurring('custom', ['interval' => 14]) // Every 14 days
    ->saveScheduled();
```

### Email Tracking

The package provides built-in tracking for email opens and link clicks.

#### Enabling Tracking

Tracking is automatically enabled when using the package. Open tracking works by embedding a tiny transparent image in emails, while link tracking works by rewriting links to pass through a tracking endpoint.

#### Tracking Opens

```php
Email::to('recipient@example.com')
    ->subject('Trackable Email')
    ->html('<p>This email will track when it is opened.</p>')
    ->send();
```

#### Tracking Links

```php
Email::to('recipient@example.com')
    ->subject('Email with Tracked Links')
    ->html('<p>Check out our <a href="https://example.com/product">latest product</a>!</p>')
    ->send();
```

The package will automatically rewrite the link to track clicks.

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

The package provides comprehensive analytics for sent emails.

#### Basic Analytics

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;

// Total emails sent
$totalSent = EmailLog::where('status', 'sent')->count();

// Total emails opened
$totalOpened = EmailLog::whereNotNull('opened_at')->count();

// Open rate
$openRate = ($totalSent > 0) ? ($totalOpened / $totalSent) * 100 : 0;

// Failed emails
$failedEmails = EmailLog::where('status', 'failed')->get();
```

#### Advanced Queries

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;
use Carbon\Carbon;

// Emails sent in the last 7 days
$recentEmails = EmailLog::where('sent_at', '>=', Carbon::now()->subDays(7))->get();

// Emails to a specific recipient
$recipientEmails = EmailLog::toAddress('specific@example.com')->get();

// Emails with a specific subject
$subjectEmails = EmailLog::withSubject('Newsletter')->get();

// Emails using a specific template
$templateEmails = EmailLog::where('template_name', 'welcome_email')->get();
```

## API Reference

### Email Facade

#### Basic Methods

| Method | Description |
|--------|-------------|
| `to($address, $name = null)` | Add a recipient |
| `cc($address, $name = null)` | Add a CC recipient |
| `bcc($address, $name = null)` | Add a BCC recipient |
| `from($address, $name = null)` | Set the sender |
| `subject($subject)` | Set the email subject |
| `view($view, $data = [])` | Use a Blade view for content |
| `html($content)` | Use raw HTML for content |
| `attach($file, $options = [])` | Attach a file |
| `attachData($data, $name, $options = [])` | Attach raw data |
| `attachFromStorage($disk, $path, $name = null, $options = [])` | Attach from Storage |
| `mailer($mailer)` | Use a specific mailer |
| `send()` | Send immediately |
| `queue($connection = null, $queue = null)` | Queue for sending |

#### Template Methods

| Method | Description |
|--------|-------------|
| `template($templateName)` | Use a database template |
| `with($data)` | Set placeholder values |

#### Scheduling Methods

| Method | Description |
|--------|-------------|
| `schedule($datetime)` | Schedule for future sending |
| `expires($datetime)` | Set expiration time |
| `recurring($frequency, $options = [])` | Set up recurring emails |
| `when($callback)` | Add a condition for sending |
| `retryAttempts($attempts)` | Set retry attempts |
| `saveScheduled()` | Save as a scheduled email |

### Models

#### EmailTemplate

| Method | Description |
|--------|-------------|
| `versions()` | Get all versions |
| `activeVersion()` | Get active version |

#### EmailTemplateVersion

| Property | Description |
|----------|-------------|
| `email_template_id` | Parent template ID |
| `version` | Version number |
| `subject` | Email subject |
| `html_content` | HTML content |
| `text_content` | Plain text content |
| `placeholders` | Available placeholders |
| `is_active` | Whether version is active |

#### EmailLog

| Method | Description |
|--------|-------------|
| `scopeOpened($query)` | Query opened emails |
| `scopeToAddress($query, $email)` | Query by recipient |
| `scopeWithSubject($query, $subject)` | Query by subject |
| `links()` | Get tracked links |

#### ScheduledEmail

| Property | Description |
|----------|-------------|
| `uuid` | Unique identifier |
| `status` | Current status |
| `scheduled_at` | When to send |
| `expires_at` | When to expire |
| `frequency` | Recurrence frequency |
| `frequency_options` | Recurrence options |
| `conditions` | Sending conditions |
| `parent_id` | Parent email for recurring |
| `occurrence_number` | Occurrence number |

## Troubleshooting

### Common Issues

#### Emails Not Being Sent

1. Check your mail configuration in `.env` and `config/mail.php`
2. Verify queue worker is running if using queued emails
3. Check the Laravel log files for errors
4. Ensure the scheduled command is running for scheduled emails

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

### Debugging Tips

1. Enable more verbose logging in `config/logging.php`
2. Use Laravel Telescope for detailed insight into queued jobs and emails
3. Inspect the database tables directly to see the state of emails and templates
4. Test with a service like Mailtrap to inspect the actual email content

### Getting Help

If you encounter issues not covered in this documentation:

1. Check the [GitHub repository](https://github.com/grim-reapper/laravel-advanced-email) for open issues
2. Submit a new issue with detailed reproduction steps
3. Reach out to the community on Laravel forums or Stack Overflow