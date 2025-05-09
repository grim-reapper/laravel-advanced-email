<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    | If null, it will use the default mailer defined in config/mail.php
    |
    */

    'default_mailer' => null,

    /*
    |--------------------------------------------------------------------------
    | Default From Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    | If null, it will use the default 'from' address from config/mail.php
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    |
    | Specify the default queue connection name to use when queuing emails.
    | If null, the default queue connection from config/queue.php will be used.
    |
    */

    'default_queue_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Queue Name
    |--------------------------------------------------------------------------
    |
    | Specify the default queue name to use when queuing emails.
    | If null, the default queue name from the connection config will be used.
    |
    */

    'default_queue_name' => null,

    /*
    |--------------------------------------------------------------------------
    | Email Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how email sending events are logged. You can disable logging,
    | log to the default Laravel log channel, or log to a database table.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Email Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how email sending events are logged. You can disable logging,
    | log to the default Laravel log channel ('log'), or log to a database table ('database').
    |
    */

    'logging' => [
        'enabled' => env('ADVANCED_EMAIL_LOGGING_ENABLED', true),
        'driver' => env('ADVANCED_EMAIL_LOGGING_DRIVER', 'database'),

        // Configuration specific to the 'database' driver
        'database' => [
            'model' => env('ADVANCED_EMAIL_LOG_MODEL', \GrimReapper\AdvancedEmail\Models\EmailLog::class),
            'connection' => env('ADVANCED_EMAIL_LOG_CONNECTION', null), // Null uses default DB connection
        ],

        // Configuration specific to the 'log' driver
        'log' => [
            'channel' => env('ADVANCED_EMAIL_LOG_CHANNEL', null), // null uses default log channel
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Define multiple mailer configurations to be used for sending emails.
    | The system will attempt to send using these providers based on the
    | specified failover strategy.
    |
    | Each provider entry should correspond to a mailer configured in
    | your main `config/mail.php` file.
    |
    */

    'providers' => [
        // Define your mailers here in the order you want them to be tried.
        // The key is the mailer name as defined in config/mail.php mailers array.
        // Example:
        // 'primary_smtp' => config('mail.mailers.smtp'), // Assuming 'smtp' is your primary
        // 'backup_mailgun' => config('mail.mailers.mailgun'), // Assuming 'mailgun' is your backup
        // Add more providers as needed
        env('MAIL_MAILER', 'smtp'), // Default to the standard Laravel mailer
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Strategy
    |--------------------------------------------------------------------------
    |
    | Define the strategy for handling failures when sending emails through
    | multiple providers.
    |
    | Supported strategies:
    | - 'sequential': Try providers in the order they are defined in the 'providers' array.
    | - 'random': (Future implementation) Pick a random provider.
    |
    */

    'failover_strategy' => 'sequential',





    /*
    |--------------------------------------------------------------------------
    | Email Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tracking for email opens and link clicks.
    |
    */
    'tracking' => [
        'enabled' => env('ADVANCED_EMAIL_TRACKING_ENABLED', true),
        'route_prefix' => env('ADVANCED_EMAIL_TRACKING_ROUTE_PREFIX', 'email-tracking'),
        'middleware' => ['web'], // Default middleware for tracking routes
        'opens' => [
            'enabled' => env('ADVANCED_EMAIL_TRACKING_OPENS_ENABLED', true),
            'route_name' => 'opens', // Route name for the tracking pixel
        ],
        'clicks' => [
            'enabled' => env('ADVANCED_EMAIL_TRACKING_CLICKS_ENABLED', true),
            'route_name' => 'clicks', // Route name for click tracking redirects
        ],
    ],



    /*
    |--------------------------------------------------------------------------
    | Database Configuration (for Models within this package)
    |--------------------------------------------------------------------------
    |
    | Configure the database connection and table names used by the package's models.
    | This allows users to customize where the package stores its data.
    |
    */
    'database' => [
        'connection' => env('ADVANCED_EMAIL_DB_CONNECTION', null), // Default connection
        'tables' => [
            'email_logs' => env('ADVANCED_EMAIL_LOGS_TABLE', 'email_logs'),
            'email_links' => env('ADVANCED_EMAIL_LINKS_TABLE', 'email_links'),
            'email_templates' => env('ADVANCED_EMAIL_TEMPLATES_TABLE', 'email_templates'),
            'email_template_versions' => env('ADVANCED_EMAIL_TEMPLATE_VERSIONS_TABLE', 'email_template_versions'),
            'scheduled_emails' => env('ADVANCED_EMAIL_SCHEDULED_EMAILS_TABLE', 'scheduled_emails'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default View Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace used when loading email views.
    |
    */

    'view_namespace' => 'advanced-email',

    /*
    |--------------------------------------------------------------------------
    | Email Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how scheduled emails are processed and managed.
    |
    */
    'scheduling' => [
        'enabled' => env('ADVANCED_EMAIL_SCHEDULING_ENABLED', true),
        'frequency' => env('ADVANCED_EMAIL_SCHEDULING_FREQUENCY', 'everyMinute'), // Scheduler frequency: everyMinute, everyFiveMinutes, everyThirtyMinutes, hourly, daily
        'batch_size' => env('ADVANCED_EMAIL_SCHEDULING_BATCH_SIZE', 50), // Number of emails to process in each batch
        
        // Retry configuration for failed emails
        'retry' => [
            'enabled' => env('ADVANCED_EMAIL_RETRY_ENABLED', true),
            'max_attempts' => env('ADVANCED_EMAIL_RETRY_MAX_ATTEMPTS', 3), // Maximum number of retry attempts
            'base_delay' => env('ADVANCED_EMAIL_RETRY_BASE_DELAY', 5), // Base delay in minutes between retries
            'max_delay' => env('ADVANCED_EMAIL_RETRY_MAX_DELAY', 120), // Maximum delay in minutes (caps exponential backoff)
            'strategy' => env('ADVANCED_EMAIL_RETRY_STRATEGY', 'exponential'), // exponential, linear, fixed
        ],
        
        'queue' => [
            'connection' => env('ADVANCED_EMAIL_SCHEDULING_QUEUE_CONNECTION', null), // Queue connection for scheduled email jobs
            'name' => env('ADVANCED_EMAIL_SCHEDULING_QUEUE_NAME', 'emails'), // Queue name for scheduled email jobs
            'priority' => env('ADVANCED_EMAIL_SCHEDULING_QUEUE_PRIORITY', 0), // Priority for scheduled email jobs
        ],
        
        // Conditional sending based on triggers/events
        'triggers' => [
            'enabled' => env('ADVANCED_EMAIL_TRIGGERS_ENABLED', true),
            'types' => [
                'event' => env('ADVANCED_EMAIL_TRIGGERS_EVENT_ENABLED', true),
                'database' => env('ADVANCED_EMAIL_TRIGGERS_DATABASE_ENABLED', true),
                'time' => env('ADVANCED_EMAIL_TRIGGERS_TIME_ENABLED', true),
                'callback' => env('ADVANCED_EMAIL_TRIGGERS_CALLBACK_ENABLED', true),
            ],
        ],
        
        // Recurring email settings
        'recurring' => [
            'enabled' => env('ADVANCED_EMAIL_RECURRING_ENABLED', true),
            'cleanup_frequency' => env('ADVANCED_EMAIL_RECURRING_CLEANUP_FREQUENCY', 'daily'), // How often to run cleanup tasks
            'max_occurrences_default' => env('ADVANCED_EMAIL_RECURRING_MAX_OCCURRENCES', 100), // Default maximum occurrences if not specified
            'frequencies' => [
                'daily' => env('ADVANCED_EMAIL_RECURRING_DAILY_ENABLED', true),
                'weekly' => env('ADVANCED_EMAIL_RECURRING_WEEKLY_ENABLED', true),
                'monthly' => env('ADVANCED_EMAIL_RECURRING_MONTHLY_ENABLED', true),
                'custom' => env('ADVANCED_EMAIL_RECURRING_CUSTOM_ENABLED', true),
            ],
            'auto_regenerate' => env('ADVANCED_EMAIL_RECURRING_AUTO_REGENERATE', true), // Automatically generate next occurrence after sending
            'default_expiry_days' => env('ADVANCED_EMAIL_RECURRING_DEFAULT_EXPIRY_DAYS', 90), // Default expiry period in days if not specified
        ],
    ],

];