<?php

namespace Pdfember\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pdfember\Pdfember;

/**
 * Registers the client from config/pdfember.php. Discovered automatically; the
 * key is checked when the client is first used, so the app boots without one.
 */
class PdfemberServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/pdfember.php', 'pdfember');

        $this->app->singleton(Pdfember::class, fn (Application $app) => new Pdfember(
            $app['config']->get('pdfember.api_key'),
            $app['config']->get('pdfember.base_url'),
            (float) $app['config']->get('pdfember.timeout', 70),
        ));
        $this->app->alias(Pdfember::class, 'pdfember');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/pdfember.php' => $this->app->configPath('pdfember.php'),
            ], 'pdfember-config');
        }
    }
}
