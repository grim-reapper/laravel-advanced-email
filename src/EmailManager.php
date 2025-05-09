<?php

namespace GrimReapper\AdvancedEmail;

use Illuminate\Support\Manager;

class EmailManager extends Manager
{
    protected $config;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->config = $app['config']['advanced_email'];
    }

    public function to($address)
    {
        return (new EmailBuilder($this->app))->to($address);
    }

    public function getDefaultDriver()
    {
        return $this->config['default'] ?? 'smtp';
    }
}