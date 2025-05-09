# Laravel Advanced Email - Usage Guide

This guide provides practical examples for using the Laravel Advanced Email package in your Laravel application.

## Table of Contents

- [Basic Email Sending](#basic-email-sending)
- [Working with Templates](#working-with-templates)
- [Email Scheduling](#email-scheduling)
- [Recurring Emails](#recurring-emails)
- [Email Tracking](#email-tracking)
- [Multi-Provider Support](#multi-provider-support)
- [Attachments](#attachments)
- [Analytics](#analytics)

## Basic Email Sending

### Simple HTML Email

```php
use GrimReapper\AdvancedEmail\Facades\Email;

Email::to('recipient@example.com')
    ->subject('Welcome to Our Application')
    ->html('<h1>Welcome!</h1><p>Thank you for signing up.</p>')
    ->send();
```

### Using Blade Templates

```php
Email::to('recipient@example.com')
    ->subject('Welcome to Our Application')
    ->view('emails.welcome', [
        'name' => 'John Doe',
        'activationLink' => 'https://example.com/activate/123'
    ])
    ->send();
```

### Multiple Recipients

```php
Email::to(['user1@example.com', 'user2@example.com' => 'User Two'])
    ->cc('manager@example.com')
    ->bcc(['admin@example.com' => 'Admin', 'support@example.com'])
    ->subject('Team Announcement')
    ->view('emails.announcement', ['message' => 'Important announcement'])
    ->send();
```

### Queueing Emails

```php
Email::to('recipient@example.com')
    ->subject('Welcome Aboard')
    ->view('emails.welcome')
    ->queue(); // Uses default queue

// With specific queue connection and name
Email::to('recipient@example.com')
    ->subject('Welcome Aboard')
    ->view('emails.welcome')
    ->queue('redis', 'emails');
```

## Working with Templates

### Creating a Template

```php
use GrimReapper\AdvancedEmail\Models\EmailTemplate;
use GrimReapper\AdvancedEmail\Models\EmailTemplateVersion;

// Create the template
$template = EmailTemplate::create([
    'name' => 'password_reset',
    'description' => 'Email sent when a user requests a password reset',
]);

// Create the first version
$version = EmailTemplateVersion::create([
    'email_template_id' => $template->id,
    'version' => 1,
    'subject' => 'Reset Your Password',
    'html_content' => '<h1>Password Reset</h1><p>Hello {{name}},</p><p>Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p>',
    'text_content' => 'Hello {{name}}, Click the following link to reset your password: {{reset_link}}',
    'placeholders' => ['name', 'reset_link'],
    'is_active' => true,
]);
```

### Using a Template

```php
Email::to('user@example.com')
    ->template('password_reset') // Use the template name
    ->with([ // Provide values for placeholders
        'name' => 'John Doe',
        'reset_link' => 'https://example.com/reset/abc123',
    ])
    ->send();
```

### Updating a Template

```php
$template = EmailTemplate::where('name', 'password_reset')->first();

// Deactivate current active version
$template->activeVersion()->update(['is_active' => false]);

// Create new version
$newVersion = EmailTemplateVersion::create([
    'email_template_id' => $template->id,
    'version' => $template->versions()->max('version') + 1,
    'subject' => 'Reset Your Password',
    'html_content' => '<h1>Password Reset Request</h1><p>Hello {{name}},</p><p>We received a request to reset your password. Click the button below to reset it:</p><div style="text-align: center;"><a href="{{reset_link}}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Reset Password</a></div><p>If you didn\'t request this, please ignore this email.</p>',
    'text_content' => 'Hello {{name}}, We received a request to reset your password. Please go to this link to reset it: {{reset_link}}',
    'placeholders' => ['name', 'reset_link'],
    'is_active' => true,
]);
```

## Email Scheduling

### Schedule a Future Email

```php
use Carbon\Carbon;

Email::to('recipient@example.com')
    ->subject('Upcoming Event Reminder')
    ->view('emails.event-reminder', ['event' => 'Annual Conference'])
    ->schedule(Carbon::now()->addDays(3)) // Send in 3 days
    ->saveScheduled();
```

### Schedule with Expiration

```php
Email::to('recipient@example.com')
    ->subject('Limited Time Offer')
    ->view('emails.promotion', ['discount' => '20%'])
    ->schedule(Carbon::tomorrow()) // Send tomorrow
    ->expires(Carbon::now()->addDays(3)) // Expire after 3 days
    ->saveScheduled();
```

### Conditional Scheduling

```php
Email::to('recipient@example.com')
    ->subject('Weather Alert')
    ->view('emails.weather-alert')
    ->schedule(Carbon::tomorrow()->setHour(7)) // Tomorrow at 7 AM
    ->when(function() {
        // Only send if the forecast shows rain
        return WeatherService::getForecast() === 'rain';
    })
    ->saveScheduled();
```

## Recurring Emails

### Daily Recurring Email

```php
Email::to('team@example.com')
    ->subject('Daily Sales Report')
    ->view('emails.daily-report')
    ->schedule(Carbon::tomorrow()->setHour(8)) // Start tomorrow at 8 AM
    ->recurring('daily') // Repeat daily
    ->saveScheduled();
```

### Weekly Recurring Email

```php
use Carbon\Carbon;

Email::to('subscribers@example.com')
    ->subject('Weekly Newsletter')
    ->template('weekly_newsletter')
    ->schedule(Carbon::next(Carbon::FRIDAY)->setHour(10)) // Next Friday at 10 AM
    ->recurring('weekly', ['days' => [Carbon::FRIDAY]]) // Every Friday
    ->saveScheduled();
```

### Monthly Recurring Email

```php
Email::to('customers@example.com')
    ->subject('Monthly Statement')
    ->view('emails.monthly-statement')
    ->schedule(Carbon::now()->firstOfMonth()->addDays(2)) // 3rd day of month
    ->recurring('monthly', ['day' => 3]) // 3rd day of each month
    ->saveScheduled();
```

### Custom Interval Recurring Email

```php
Email::to('recipient@example.com')
    ->subject('Bi-weekly Check-in')
    ->html('<p>This is your bi-weekly check-in reminder.</p>')
    ->schedule(Carbon::now()->addDays(1))
    ->recurring('custom', ['interval' => 14]) // Every 14 days
    ->saveScheduled();
```

## Email Tracking

### Tracking Email Opens

Open tracking is automatically enabled for all HTML emails:

```php
Email::to('prospect@example.com')
    ->subject('Our Latest Offerings')
    ->view('emails.offerings')
    ->send();
```

### Tracking Link Clicks

Link tracking is also automatic. The package rewrites links to pass through a tracking endpoint:

```php
Email::to('customer@example.com')
    ->subject('Special Offer Inside')
    ->html('<p>Check out our <a href="https://example.com/special-offer">special offer</a> today!</p>')
    ->send();
```

### Retrieving Tracking Data

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;

// Get all opened emails
$openedEmails = EmailLog::opened()->get();

// Get open rate for a specific period
$totalSent = EmailLog::where('sent_at', '>=', now()->subDays(30))->count();
$totalOpened = EmailLog::where('sent_at', '>=', now()->subDays(30))
    ->whereNotNull('opened_at')
    ->count();
$openRate = ($totalSent > 0) ? ($totalOpened / $totalSent) * 100 : 0;

// Get click data for a specific email
$email = EmailLog::where('uuid', $uuid)->first();
$clickedLinks = $email->links()->where('click_count', '>', 0)->get();

// Get total clicks for each link
foreach ($clickedLinks as $link) {
    echo "Link: {$link->original_url} - Clicks: {$link->click_count}\n";
}
```

## Multi-Provider Support

### Automatic Failover

With proper configuration, the package will automatically try each provider in sequence if previous ones fail:

```php
Email::to('important@example.com')
    ->subject('Critical System Notification')
    ->html('<p>This is a critical system notification that must be delivered.</p>')
    ->send(); // Will try multiple providers if needed
```

### Specifying a Provider

```php
Email::mailer('postmark') // Use Postmark specifically
    ->to('recipient@example.com')
    ->subject('Via Postmark')
    ->html('<p>This email will only use Postmark.</p>')
    ->send();
```

## Attachments

### File Attachments

```php
Email::to('recipient@example.com')
    ->subject('Contract for Review')
    ->view('emails.contract')
    ->attach('/path/to/contract.pdf', [
        'as' => 'company_contract.pdf',
        'mime' => 'application/pdf'
    ])
    ->send();
```

### Raw Data Attachments

```php
// Generate PDF content
$pdf = PDF::loadView('pdfs.invoice', ['invoice' => $invoice]);
$pdfContent = $pdf->output();

Email::to('client@example.com')
    ->subject('Your Invoice')
    ->view('emails.invoice', ['invoice' => $invoice])
    ->attachData($pdfContent, 'invoice.pdf', ['mime' => 'application/pdf'])
    ->send();
```

### Storage Attachments

```php
// Assuming the file exists in storage
Email::to('recipient@example.com')
    ->subject('Requested Documents')
    ->view('emails.documents')
    ->attachFromStorage('documents', 'user/123/document.docx', 'requested_document.docx')
    ->send();
```

### Multiple Attachments

```php
Email::to('recipient@example.com')
    ->subject('Project Files')
    ->view('emails.project')
    ->attach('/path/to/document1.pdf')
    ->attach('/path/to/document2.xlsx')
    ->attachFromStorage('projects', 'project123/diagram.png')
    ->send();
```

## Analytics

### Basic Email Statistics

```php
use GrimReapper\AdvancedEmail\Models\EmailLog;
use Carbon\Carbon;

// Total emails sent today
$todaySent = EmailLog::whereDate('sent_at', Carbon::today())->count();

// Total emails opened
$totalOpened = EmailLog::whereNotNull('opened_at')->count();

// Open rate
$totalSent = EmailLog::where('status', 'sent')->count();
$openRate = ($totalSent > 0) ? ($totalOpened / $totalSent) * 100 : 0;

// Failed emails
$failedEmails = EmailLog::where('status', 'failed')->get();
```

### Recipient Analysis

```php
// Emails to a specific domain
$domainEmails = EmailLog::where('to', 'like', '%@example.com%')->get();

// Most engaged recipients (by opens)
$engagedRecipients = EmailLog::whereNotNull('opened_at')
    ->selectRaw('to, COUNT(*) as open_count')
    ->groupBy('to')
    ->orderByDesc('open_count')
    ->take(10)
    ->get();
```

### Template Performance

```php
// Performance by template
$templatePerformance = EmailLog::whereNotNull('template_name')
    ->selectRaw('template_name, COUNT(*) as sent_count, SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count')
    ->groupBy('template_name')
    ->get()
    ->map(function ($item) {
        $item->open_rate = ($item->sent_count > 0) ? ($item->opened_count / $item->sent_count) * 100 : 0;
        return $item;
    });
```

### Link Click Analysis

```php
use GrimReapper\AdvancedEmail\Models\EmailLink;

// Most clicked links
$popularLinks = EmailLink::where('click_count', '>', 0)
    ->orderByDesc('click_count')
    ->take(10)
    ->get();

// Click-through rate for a specific campaign
$campaignEmails = EmailLog::where('subject', 'like', '%Spring Sale%')->get();
$emailIds = $campaignEmails->pluck('id');

$totalRecipients = $campaignEmails->count();
$clickedEmails = EmailLink::whereIn('email_log_id', $emailIds)
    ->where('click_count', '>', 0)
    ->distinct('email_log_id')
    ->count();

$clickThroughRate = ($totalRecipients > 0) ? ($clickedEmails / $totalRecipients) * 100 : 0;
```

This guide covers the most common usage scenarios for the Laravel Advanced Email package. For more detailed information, refer to the [full documentation](documentation.md).