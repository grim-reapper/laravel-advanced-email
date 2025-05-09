<?php

namespace GrimReapper\AdvancedEmail\Facades;

use Illuminate\Support\Facades\Facade;
use GrimReapper\AdvancedEmail\Contracts\EmailBuilder as EmailBuilderContract;

/**
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder to(string|array $address, string|null $name = null)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder cc(string|array $address, string|null $name = null)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder bcc(string|array $address, string|null $name = null)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder from(string $address, string|null $name = null)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder subject(string $subject)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder view(string $view, array $data = [])
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder html(string $htmlContent)
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder attach(string $file, array $options = [])
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder attachData(string $data, string $name, array $options = [])
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder attachFromStorage(string $disk, string $path, string|null $name = null, array $options = [])
 * @method static \GrimReapper\AdvancedEmail\Contracts\EmailBuilder mailer(string $mailer)
 * @method static void send()
 * @method static void queue(string|null $connection = null, string|null $queue = null)
 *
 * @see \GrimReapper\AdvancedEmail\Services\EmailService
 */
class Email extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return EmailBuilderContract::class;
    }
}