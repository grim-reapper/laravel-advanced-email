# Laravel Advanced Email - Installation Guide

This guide provides detailed instructions for installing and configuring the Laravel Advanced Email package in your Laravel application.

## Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher
- Composer

## Step 1: Install via Composer

Install the package using Composer:

```bash
composer require grim-reapper/laravel-advanced-email
```

## Step 2: Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="config"
```

This will create a `config/advanced_email.php` file in your application.

## Step 3: Publish Views (Optional)

If you want to customize the example email views:

```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="views"
```

## Step 4: Run Migrations

Run the database migrations to create the necessary tables:

```bash
php artisan migrate
```

This will create the following tables:
- `email_templates` - Stores email template metadata
- `email_template_versions` - Stores versioned content for email templates
- `email_logs` - Logs all sent emails
- `email_links` - Tracks links in emails for click analytics
- `scheduled_emails` - Stores emails scheduled for future delivery

## Step 5: Configure Environment Variables

Add the following variables to your `.env` file to customize the package behavior:

```
# Advanced Email Configuration
ADVANCED_EMAIL_LOGGING_ENABLED=true
ADVANCED_EMAIL_LOGGING_DRIVER=database
```

## Step 6: Configure Laravel Scheduler (for Email Scheduling)

If you plan to use the email scheduling features, make sure Laravel's scheduler is running. Add the following Cron entry to your server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Or for Windows environments, set up a scheduled task that runs the following command every minute:

```
php artisan schedule:run
```

## Step 7: Configure Queue (Recommended)

For optimal performance, configure a queue driver in your Laravel application. Update your `.env` file:

```
QUEUE_CONNECTION=database
```

Run the queue migrations if you haven't already:

```bash
php artisan queue:table
php artisan migrate
```

Start a queue worker:

```bash
php artisan queue:work
```

For production, consider using a process monitor like Supervisor to keep the queue worker running.

## Step 8: Configure Mail Providers

Ensure your mail configuration is set up correctly in `config/mail.php`. For multi-provider support, configure multiple mailers:

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

Then update your `config/advanced_email.php` to specify the provider order:

```php
'providers' => ['smtp', 'postmark', 'ses'],
'failover_strategy' => 'sequential',
```

## Verification

To verify that the package is installed correctly, you can send a test email:

```php
use GrimReapper\AdvancedEmail\Facades\Email;

Email::to('test@example.com')
    ->subject('Test Email')
    ->html('<h1>Test Email</h1><p>This is a test email from Laravel Advanced Email.</p>')
    ->send();
```

Check your database to confirm that the email was logged correctly.

## Next Steps

Now that you have installed and configured the Laravel Advanced Email package, you can:

1. Create email templates in the database
2. Set up scheduled and recurring emails
3. Implement email tracking in your application
4. Configure analytics for your email campaigns

Refer to the [full documentation](documentation.md) for detailed usage instructions and examples.