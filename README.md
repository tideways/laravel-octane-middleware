# Tideways Middleware for Laravel Octane

This package connects a Laravel Octance application with Tideways for PHP
Monitoring, Profiling and Exception Tracking.

It provides a Laravel HTTP middleware that starts Tideways based on the Laravel
Request object state.

## Installation

You can install the package via composer:

    composer require tideways/laravel-octane-middleware

## Usage

Register the Middleware in your Laravel Middleware stack:

```php
class Kernel extends HttpKernel
{
    protected $middleware = [
        \Tideways\LaravelOctane\OctaneMiddleware::class,
        // ...
    ];

    // ...
}
```

Install Tideways PHP extension for PHP 8 and configure the API Key
via php.ini or additional configuration files tideways.ini:

```
tideways.api_key=abcdefg
```

More details on how to install and get the API Key in the Tideways docs:

https://support.tideways.com/documentation/setup/installation/api-key.html

## Known Issues

* Triggering traces via Chrome Extension requires Tideways PHP Extension version 5.3.16 and up
* Laravel framework spans and events in Profiler require Tideways PHP Extension version 5.3.16 and up

## License

The MIT License (MIT). Please see License File for more information.
