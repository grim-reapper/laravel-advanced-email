# Laravel Advanced Email - Configuration Guide

This guide explains all the configuration options available in the Laravel Advanced Email package.

## Configuration File

After publishing the configuration file using the command below, you can find it at `config/advanced_email.php`:

```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="config"
```

## Available Configuration Options

### Default Mailer

```php
'default_mailer' => null,
```

Specifies which mailer to use by default. If set to `null`, the package will use Laravel's default mailer as configured in `config/mail.php`.

### Default From Address

```php
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```

Sets the default sender address and name for all emails. By default, it uses the values from your Laravel mail configuration.

### Queue Configuration

```php
'default_queue_connection' => null, // Uses default queue connection if null
'default_queue_name' => null, // Uses default queue name if null
```

Specifies the default queue connection and queue name to use when queueing emails. If set to `null`, the package will use Laravel's default queue configuration.

### Logging Configuration

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

- `enabled`: Enable or disable email logging
- `driver`: Choose between `database` (store logs in database) or `log` (use Laravel's logging system)
- `database.model`: The model class to use for database logging
- `database.connection`: The database connection to use for logging (null = default connection)
- `log.channel`: The Laravel log channel to use when driver is set to `log`

### Multi-Provider Configuration

```php
'providers' => [
    // List of mail providers to use, in order of preference
    'smtp', // Default Laravel mailer
    'secondary_smtp', // Additional configured mailers
],

'failover_strategy' => 'sequential', // Currently only sequential is supported
```

- `providers`: An array of mailer names to use, in order of preference. These should correspond to mailers configured in `config/mail.php`.
- `failover_strategy`: The strategy to use when a provider fails. Currently only `sequential` is supported, which tries each provider in sequence until one succeeds.

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

- `frequency`: How often to check for scheduled emails. This should be a valid Laravel scheduler frequency method (e.g., `everyMinute`, `hourly`, `daily`).
- `batch_size`: The number of scheduled emails to process in each batch.
- `retry.enabled`: Whether to retry failed scheduled emails.
- `retry.max_attempts`: The maximum number of retry attempts for failed scheduled emails.
- `retry.delay`: The delay in seconds between retry attempts.

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

- `connection`: The database connection to use for all package tables (null = default connection).
- `tables`: Customize the table names used by the package.

## Environment Variables

You can configure many aspects of the package using environment variables in your `.env` file:

```
# Advanced Email Configuration
ADVANCED_EMAIL_LOGGING_ENABLED=true
ADVANCED_EMAIL_LOGGING_DRIVER=database
ADVANCED_EMAIL_LOG_MODEL=\GrimReapper\AdvancedEmail\Models\EmailLog
ADVANCED_EMAIL_LOG_CONNECTION=null
ADVANCED_EMAIL_LOG_CHANNEL=null
```

## Multi-Provider Configuration Example

To set up multiple email providers with failover, first configure your mailers in `config/mail.php`:

```php
'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'timeout' => null,
    ],
    
    'postmark' => [
        'transport' => 'postmark',
        'token' => env('POSTMARK_TOKEN'),
    ],
    
    'ses' => [
        'transport' => 'ses',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
],
```

Then configure the provider order in `config/advanced_email.php`:

```php
'providers' => ['smtp', 'postmark', 'ses'],
'failover_strategy' => 'sequential',
```

With this configuration, the package will first try to send emails using the `smtp` mailer. If that fails, it will try `postmark`, and if that fails, it will try `ses`.

## Customizing Email Tracking

Email tracking is enabled by default. The package adds a tracking pixel to HTML emails and rewrites links to track clicks. You can customize this behavior by extending the package's service provider.

## Scheduling Configuration Example

To configure the package to check for scheduled emails every 5 minutes and process up to 100 emails at a time:

```php
'scheduling' => [
    'frequency' => 'everyFiveMinutes',
    'batch_size' => 100,
    'retry' => [
        'enabled' => true,
        'max_attempts' => 5,
        'delay' => 600, // 10 minutes in seconds
    ],
],
```

Make sure to run the Laravel scheduler for this to work:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Advanced Configuration

For more advanced configuration needs, you can extend the package's core classes and bind your custom implementations in a service provider:

```php
use GrimReapper\AdvancedEmail\Services\EmailService;
use App\Services\CustomEmailService;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(EmailService::class, CustomEmailService::class);
    }
}
```

This allows you to customize the package's behavior without modifying the package code directly.