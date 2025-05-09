# Laravel Advanced Email

A powerful package that enhances Laravel's email capabilities with advanced features for enterprise-level email management.

## Features

- **Template Management**: Database-driven email templates with versioning support
- **Advanced Scheduling**: Schedule one-time and recurring emails with conditions
- **Multi-Provider Support**: Automatic failover between multiple email providers
- **Email Tracking**: Track email opens and link clicks for analytics
- **Comprehensive Analytics**: Detailed reporting on email performance
- **Attachment Handling**: Multiple ways to attach files to emails

## Installation

```bash
composer require grim-reapper/laravel-advanced-email
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="GrimReapper\AdvancedEmail\AdvancedEmailServiceProvider" --tag="config"
```

Run the migrations:

```bash
php artisan migrate
```

## Basic Usage

```php
use GrimReapper\AdvancedEmail\Facades\Email;

Email::to('recipient@example.com')
    ->subject('Welcome to Our Application')
    ->html('<h1>Welcome!</h1><p>Thank you for signing up.</p>')
    ->send();
```

## Documentation

For detailed documentation, please refer to the following guides:

- [Full Documentation](docs/documentation.md)
- [Installation Guide](docs/installation.md)
- [Configuration Guide](docs/configuration-guide.md)
- [Usage Guide](docs/usage-guide.md)
- [API Reference](docs/api-reference.md)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.