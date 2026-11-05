<?php

namespace Pdfember\Tests;

use Orchestra\Testbench\TestCase;
use Pdfember\Exceptions\InvalidApiKeyException;
use Pdfember\Laravel\Facades\Pdfember as PdfemberFacade;
use Pdfember\Laravel\PdfemberServiceProvider;
use Pdfember\Pdfember;

class LaravelTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PdfemberServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Pdfember' => PdfemberFacade::class];
    }

    public function test_the_config_is_merged_with_the_production_base_url(): void
    {
        $this->assertSame('https://pdfember.com/api/v1', config('pdfember.base_url'));
        $this->assertSame(70.0, config('pdfember.timeout'));
    }

    public function test_the_client_is_a_singleton_built_from_the_config(): void
    {
        config(['pdfember.api_key' => 'pdx_live_0123456789012345678901234567890123456789']);

        $this->assertInstanceOf(Pdfember::class, $this->app->make(Pdfember::class));
        $this->assertSame($this->app->make(Pdfember::class), $this->app->make('pdfember'));
        $this->assertSame($this->app->make(Pdfember::class), PdfemberFacade::getFacadeRoot());
    }

    public function test_the_app_boots_without_a_key_but_the_client_asks_for_one(): void
    {
        config(['pdfember.api_key' => null]);

        $this->expectException(InvalidApiKeyException::class);
        $this->expectExceptionMessage('PDFEMBER_API_KEY');

        $this->app->make(Pdfember::class);
    }

    public function test_the_config_can_be_published(): void
    {
        $paths = PdfemberServiceProvider::pathsToPublish(PdfemberServiceProvider::class, 'pdfember-config');

        $this->assertSame([realpath(__DIR__.'/../config/pdfember.php')], array_map('realpath', array_keys($paths)));
        $this->assertSame([config_path('pdfember.php')], array_values($paths));
    }
}
