<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub;

use AnselmiDev\Sumsub\Console\SimulateWebhookCommand;
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Contracts\SumsubClientInterface;
use AnselmiDev\Sumsub\Http\Client\SumsubClient;
use AnselmiDev\Sumsub\Repositories\SumsubApplicantRepository;
use AnselmiDev\Sumsub\Services\SumsubService;
use Illuminate\Support\ServiceProvider;

class SumsubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/sumsub.php', 'sumsub');

        $this->app->singleton(SumsubClientInterface::class, fn ($app) => new SumsubClient(
            appToken: config('sumsub.app_token'),
            secretKey: config('sumsub.secret_key'),
            baseUrl: config('sumsub.base_url'),
        ));

        $this->app->bind(KycRepositoryInterface::class, SumsubApplicantRepository::class);

        $this->app->singleton(SumsubService::class, fn ($app) => new SumsubService(
            client: $app->make(SumsubClientInterface::class),
            repository: $app->make(KycRepositoryInterface::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sumsub');

        $this->registerLivewireComponents();
        $this->registerPublishables();

        if ($this->app->runningInConsole()) {
            $this->commands([SimulateWebhookCommand::class]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Register Livewire components when the Livewire package is present.
     * Component tag: <livewire:sumsub.kyc-verification />
     */
    private function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::component(
            'sumsub.kyc-verification',
            \AnselmiDev\Sumsub\Livewire\KycVerification::class,
        );
    }

    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // php artisan vendor:publish --tag=sumsub-config
        $this->publishes([
            __DIR__.'/Config/sumsub.php' => config_path('sumsub.php'),
        ], 'sumsub-config');

        // php artisan vendor:publish --tag=sumsub-migrations
        $this->publishes([
            __DIR__.'/database/migrations' => database_path('migrations'),
        ], 'sumsub-migrations');

        // php artisan vendor:publish --tag=sumsub-views
        // Publishes to resources/views/vendor/sumsub/
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/sumsub'),
        ], 'sumsub-views');
    }
}
