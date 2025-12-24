<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook;

use Cline\Webhook\Client\Http\Controllers\WebhookController;
use Cline\Webhook\Client\Validators\Ed25519Validator;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for webhook package.
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('webhook')
            ->hasConfigFile()
            ->hasMigration('create_webhook_calls_table');
    }

    /**
     * Register package services.
     */
    public function packageRegistered(): void
    {
        $this->registerRouteMacro();
        $this->registerValidators();
    }

    /**
     * Register route macro for easy webhook endpoint setup.
     */
    private function registerRouteMacro(): void
    {
        Route::macro('webhooks', function (string $url, string $configName = 'default'): void {
            Route::post($url, WebhookController::class)
                ->withoutMiddleware([VerifyCsrfToken::class])
                ->name('webhook.' . $configName);
        });
    }

    /**
     * Register signature validators in container.
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
}
