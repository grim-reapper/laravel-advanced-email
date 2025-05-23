<?php

namespace GrimReapper\AdvancedEmail;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Scheduling\Schedule;
use GrimReapper\AdvancedEmail\Listeners\EmailLogger;
use GrimReapper\AdvancedEmail\Services\EmailService;
use GrimReapper\AdvancedEmail\Contracts\EmailBuilder as EmailBuilderContract;
use GrimReapper\AdvancedEmail\Events\EmailSent;
use GrimReapper\AdvancedEmail\Listeners\LogEmailSent;
use GrimReapper\AdvancedEmail\Console\Commands\ProcessScheduledEmailsCommand;
use GrimReapper\AdvancedEmail\Console\Commands\ManageRecurringEmailsCommand;
use GrimReapper\AdvancedEmail\Console\Commands\ProcessAbTestsCommand; // Add this line
use Illuminate\Support\Facades\Route; // Add Route facade

class AdvancedEmailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Ensure config file exists before merging
        $configPath = __DIR__.'/../config/advanced_email.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'advanced_email');
        } else {
            // Fallback to default config if file doesn't exist
            $this->app['config']->set('advanced_email', [
                'logging' => [
                    'enabled' => true,
                    'driver' => 'database',
                    'database' => [
                        'table' => 'email_logs',
                        'connection' => null
                    ]
                ]
            ]);
        }

        $this->app->singleton(EmailBuilderContract::class, function ($app) {
            return new EmailService($app['mail.manager'], $app['config']->get('advanced_email', []));
        });

        $this->app->alias(EmailBuilderContract::class, 'advanced.email');

        $this->registerMigrations();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/advanced_email.php' => config_path('advanced_email.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/advanced-email'),
            ], 'views');
            
            // Register commands
            $this->commands([
                ProcessScheduledEmailsCommand::class,
                ManageRecurringEmailsCommand::class,
                ProcessAbTestsCommand::class, // Add this line
            ]);
            
            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'migrations');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'advanced-email');

        // Register the event subscriber for logging
        Event::subscribe(EmailLogger::class);

        
        
        // Register scheduler for processing scheduled emails
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $frequency = config('advanced_email.scheduling.frequency', 'everyMinute');
            
            // Schedule the command to run at the configured frequency
            if (method_exists($schedule, $frequency)) {
                $schedule->command('email:process-scheduled')
                    ->$frequency()
                    ->withoutOverlapping()
                    ->runInBackground();
            } else {
                // Default to every minute if the configured frequency is invalid
                $schedule->command('email:process-scheduled')
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->runInBackground();
            }
            
            // Schedule recurring email maintenance tasks
            if (config('advanced_email.scheduling.enabled', true)) {
                // Run cleanup of expired campaigns daily
                $schedule->command('email:manage-recurring --cleanup')
                    ->daily()
                    ->withoutOverlapping()
                    ->runInBackground();
                    
                // Process batch of recurring emails hourly
                if (config('advanced_email.scheduling.recurring.enabled', true)) {
                    $batchSize = config('advanced_email.scheduling.recurring.batch_size', 100);
                    $schedule->command("email:manage-recurring --batch={$batchSize}")
                        ->hourly()
                        ->withoutOverlapping()
                        ->runInBackground();
                }
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    protected function registerMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [EmailBuilderContract::class, 'advanced.email'];
    }

    
}