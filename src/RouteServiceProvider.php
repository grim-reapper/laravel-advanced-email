<?php

namespace GrimReapper\AdvancedEmail;
use GrimReapper\AdvancedEmail\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        if (config('advanced_email.tracking.enabled', false)) {
            Route::group($this->routeConfiguration(), function () {
                require __DIR__.'/../routes/web.php';
            });
        }
    }

    /**
     * Get the route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        return [
            'prefix' => config('advanced_email.tracking.route_prefix', 'email-tracking'),
            'middleware' => config('advanced_email.tracking.middleware', ['web']),
            'as' => 'advanced_email.tracking.',
        ];
    }
}