<?php namespace Keypoint\LaravelLocalizationHelpers;

use Illuminate\Support\ServiceProvider;
use Keypoint\LaravelLocalizationHelpers\Command\LocalizationClear;
use Keypoint\LaravelLocalizationHelpers\Command\LocalizationFind;
use Keypoint\LaravelLocalizationHelpers\Command\LocalizationMissing;
use Keypoint\LaravelLocalizationHelpers\Factory\Localization;
use Keypoint\LaravelLocalizationHelpers\Factory\MessageBag;

class LaravelLocalizationHelpersServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     *
     * @codeCoverageIgnore
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('laravel-localization-helpers.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     *
     * @codeCoverageIgnore
     */
    public function register()
    {
        $this->app->singleton('localization.command.missing', function ($app) {
            return new LocalizationMissing($app['config']);
        });

        $this->app->singleton('localization.command.find', function ($app) {
            return new LocalizationFind($app['config']);
        });

        $this->app->singleton('localization.command.clear', function ($app) {
            return new LocalizationClear($app['config']);
        });

        $this->commands(
            'localization.command.missing',
            'localization.command.find',
            'localization.command.clear'
        );

        $this->app->singleton('localization.helpers', function ($app) {
            return new Localization(new MessageBag());
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/config.php',
            'laravel-localization-helpers'
        );
    }
}
