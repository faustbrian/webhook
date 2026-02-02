<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook;

use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Cline\Webhook\Client\Http\Controllers\WebhookController;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Client\Validators\Ed25519Validator;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function config;
use function sprintf;

/**
 * Laravel service provider for webhook package.
 *
 * Registers package configuration, migrations, route macros, validators,
 * and model key mappings. Provides convenient webhook endpoint registration
 * via Route::webhooks() macro that automatically handles CSRF exclusion and
 * controller binding.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package metadata and resources.
     *
     * Registers the package name, configuration file, and database migrations
     * with Laravel's package service provider infrastructure.
     *
     * @param Package $package Package configuration instance
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('webhook')
            ->hasConfigFile()
            ->hasMigration('create_webhook_calls_table');
    }

    /**
     * Register package services during application bootstrapping.
     *
     * Registers route macros, signature validators, and variable key mappings
     * when the package is loaded by Laravel's service container.
     */
    public function packageRegistered(): void
    {
        $this->registerRouteMacro();
        $this->registerValidators();
    }

    /**
     * Bootstrap package services after all providers registered.
     *
     * Registers variable key mappings after VariableKeysServiceProvider
     * has registered its singleton to ensure proper initialization.
     */
    public function packageBooted(): void
    {
        $this->registerVariableKeyMappings();
    }

    /**
     * Register the Route::webhooks() macro for easy endpoint setup.
     *
     * Adds a fluent macro to Laravel's Route facade that creates webhook
     * endpoints with proper CSRF exclusion and controller binding. Usage:
     * Route::webhooks('/webhooks/stripe', 'stripe')
     */
    private function registerRouteMacro(): void
    {
        Route::macro('webhooks', function (string $url, string $configName = 'default'): void {
            Route::post($url, WebhookController::class)
                ->withoutMiddleware([VerifyCsrfToken::class])
                ->name('webhook.'.$configName);
        });
    }

    /**
     * Register signature validators in the service container.
     *
     * Binds Ed25519Validator to the container with configuration-driven public
     * key and timestamp tolerance settings. Validators are lazily instantiated
     * when resolved from the container.
     */
    private function registerValidators(): void
    {
        // Register Ed25519Validator with public key from config
        $this->app->bind(function (): Ed25519Validator {
            $configName = 'default'; // Could be made contextual

            /** @var string $publicKey */
            $publicKey = Config::get(sprintf('webhook.client.configs.%s.ed25519_public_key', $configName));

            /** @var int $tolerance */
            $tolerance = Config::get(sprintf('webhook.client.configs.%s.timestamp_tolerance_seconds', $configName), 300);

            return new Ed25519Validator(
                $publicKey,
                new TimestampValidator($tolerance),
            );
        });
    }

    /**
     * Register variable key type mappings for webhook models.
     *
     * Configures the primary key type (ULID, UUID, etc.) for the WebhookCall
     * model using the cline/variable-keys package. This enables flexible key
     * types based on application configuration.
     */
    private function registerVariableKeyMappings(): void
    {
        /** @var string $primaryKeyType */
        $primaryKeyType = config('webhook.primary_key_type', 'ulid');

        VariableKeys::map([
            WebhookCall::class => [
                'primary_key_type' => PrimaryKeyType::from($primaryKeyType),
            ],
        ]);
    }
}
