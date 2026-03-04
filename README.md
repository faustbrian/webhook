[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Laravel Webhook Package

Standard-compliant Laravel webhook client and server with HMAC-SHA256 and Ed25519 signature support.

## Features

### Standard Webhooks Compliance
- Fully compliant with [Standard Webhooks specification](https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md)
- Required headers: `webhook-id`, `webhook-timestamp`, `webhook-signature`
- Signature formats: `v1,<base64>` (HMAC) and `v1a,<base64>` (Ed25519)
- Timestamp validation for replay attack prevention
- webhook-id as idempotency key

### Server Features (Sending Webhooks)
- Fluent API for webhook dispatch
- Async dispatch via Laravel queues (default) + sync option
- Conditional dispatch: `dispatchIf()`, `dispatchUnless()`
- HMAC-SHA256 and Ed25519 signature generation
- Automatic retry with exponential backoff
- SSL verification with mutual TLS support
- Events: DispatchingWebhookCallEvent, WebhookCallSucceededEvent, WebhookCallFailedEvent, FinalWebhookCallFailedEvent
- Custom headers, proxy support, raw body transmission
- Metadata attachment, tagging for Laravel Horizon

### Client Features (Receiving Webhooks)
- Route macro: `Route::webhooks()` with CSRF bypass
- Signature verification (both HMAC and Ed25519)
- Request validation and filtering via WebhookProfile
- Database storage in `webhook_calls` table
- Async processing via ProcessWebhookJob
- Configurable response handlers
- Selective header storage
- Automatic pruning with MassPrunable trait
- Multi-tenant support (multiple webhook endpoints)

## Requirements

> **Requires [PHP 8.3+](https://php.net/releases/)**

## Installation

```bash
composer require cline/webhook
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="webhook-config"
```

Run the migrations:

```bash
php artisan migrate
```

## Usage

### Server Side (Sending Webhooks)

#### Basic Usage

```php
use Cline\Webhook\Server\WebhookCall;

WebhookCall::create()
    ->url('https://example.com/webhooks')
    ->payload(['event' => 'user.created', 'user_id' => 123])
    ->dispatch();
```

#### Advanced Usage

```php
use Cline\Webhook\Server\WebhookCall;
use Cline\Webhook\Enums\SignatureVersion;

WebhookCall::create()
    ->url('https://example.com/webhooks')
    ->payload([
        'event' => 'order.completed',
        'order_id' => 456,
        'total' => 99.99
    ])
    ->useSecret('your-secret-key')
    ->signatureVersion(SignatureVersion::V1_HMAC)
    ->withHeaders(['X-Custom-Header' => 'value'])
    ->meta(['internal_ref' => 'ABC123'])
    ->tags(['orders', 'high-priority'])
    ->maximumTries(5)
    ->timeoutInSeconds(10)
    ->onQueue('webhooks')
    ->dispatch();
```

#### Conditional Dispatch

```php
WebhookCall::create()
    ->url('https://example.com/webhooks')
    ->payload(['event' => 'user.updated'])
    ->dispatchIf($user->shouldNotifyWebhook());
```

#### Synchronous Dispatch

```php
WebhookCall::create()
    ->url('https://example.com/webhooks')
    ->payload(['event' => 'critical.alert'])
    ->dispatchSync();
```

#### Ed25519 Signatures

```php
use Cline\Webhook\Enums\SignatureVersion;

WebhookCall::create()
    ->url('https://example.com/webhooks')
    ->payload(['event' => 'secure.event'])
    ->signatureVersion(SignatureVersion::V1A_ED25519)
    ->useEd25519Key(env('WEBHOOK_ED25519_PRIVATE_KEY'))
    ->dispatch();
```

### Client Side (Receiving Webhooks)

#### Setup Routes

In your `routes/web.php` or `routes/api.php`:

```php
use Illuminate\Support\Facades\Route;

Route::webhooks('webhooks/github', 'github');
Route::webhooks('webhooks/stripe', 'stripe');
```

This creates POST routes that:
- Bypass CSRF verification
- Automatically verify signatures
- Store webhook in database
- Queue processing job

#### Configure Webhook Endpoints

In `config/webhook.php`:

```php
'client' => [
    'configs' => [
        'github' => [
            'signing_secret' => env('GITHUB_WEBHOOK_SECRET'),
            'signature_validator' => HmacValidator::class,
            'webhook_profile' => ProcessEverything::class,
            'webhook_response' => DefaultResponse::class,
            'webhook_model' => WebhookCall::class,
            'process_webhook_job' => ProcessWebhookJob::class,
            'store_headers' => ['X-GitHub-Event', 'X-GitHub-Delivery'],
            'delete_after_days' => 30,
            'timestamp_tolerance_seconds' => 300,
        ],
        'stripe' => [
            'signing_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // ... similar configuration
        ],
    ],
],
```

#### Process Webhooks

Create a custom processor by implementing the `ProcessesWebhook` interface:

```php
namespace App\Webhooks;

use Cline\Webhook\Client\Contracts\ProcessesWebhook;
use Cline\Webhook\Client\Models\WebhookCall;

class GitHubWebhookProcessor implements ProcessesWebhook
{
    public function process(WebhookCall $webhookCall): void
    {
        $payload = $webhookCall->payload;

        match($payload['event']) {
            'push' => $this->handlePush($payload),
            'pull_request' => $this->handlePullRequest($payload),
            default => null,
        };
    }

    private function handlePush(array $payload): void
    {
        // Handle push event
    }

    private function handlePullRequest(array $payload): void
    {
        // Handle PR event
    }
}
```

Register in config:

```php
'github' => [
    // ...
    'webhook_processor' => \App\Webhooks\GitHubWebhookProcessor::class,
],
```

#### Custom Webhook Profiles (Filtering)

```php
namespace App\Webhooks;

use Cline\Webhook\Client\Contracts\WebhookProfile;
use Illuminate\Http\Request;

class OnlyProductionWebhooks implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = json_decode($request->getContent(), true);

        return $payload['environment'] === 'production';
    }
}
```

#### Listen to Events

```php
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Events\WebhookProcessedEvent;
use Cline\Webhook\Server\Events\WebhookCallFailedEvent;

Event::listen(WebhookReceivedEvent::class, function ($event) {
    Log::info('Webhook received', [
        'id' => $event->webhookCall->webhook_id,
        'config' => $event->configName,
    ]);
});

Event::listen(WebhookCallFailedEvent::class, function ($event) {
    Log::error('Webhook dispatch failed', [
        'url' => $event->url,
        'attempt' => $event->attempt,
    ]);
});
```

#### Query Webhooks

```php
use Cline\Webhook\Client\Models\WebhookCall;

// Get pending webhooks
$pending = WebhookCall::pending()->get();

// Get webhooks for specific config
$githubWebhooks = WebhookCall::forConfig('github')->get();

// Find by webhook ID (idempotency)
$webhook = WebhookCall::byWebhookId('01HQWE...')->first();

// Retry failed webhook
$webhook = WebhookCall::failed()->first();
$webhook->clearException();
$webhook->update(['status' => WebhookStatus::PENDING]);
dispatch(new ProcessWebhookJob($webhook));
```

#### Automatic Pruning

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('model:prune', ['--model' => \Cline\Webhook\Client\Models\WebhookCall::class])
        ->daily();
}
```

## Configuration

### Server Configuration

```php
'server' => [
    'queue' => env('WEBHOOK_QUEUE'), // Queue for async dispatch
    'http_verb' => 'POST', // HTTP method
    'timeout_in_seconds' => 3, // Request timeout
    'tries' => 3, // Max retry attempts
    'backoff_strategy' => ExponentialBackoffStrategy::class,
    'verify_ssl' => true, // Verify SSL certificates
    'throw_exception_on_failure' => false, // Throw on final failure
    'signature_version' => SignatureVersion::V1_HMAC->value,
    'signing_secret' => env('WEBHOOK_SIGNING_SECRET'),
    'ed25519_private_key' => env('WEBHOOK_ED25519_PRIVATE_KEY'),
],
```

### Client Configuration

```php
'client' => [
    'configs' => [
        'default' => [
            'signing_secret' => env('WEBHOOK_SECRET'),
            'signature_validator' => HmacValidator::class,
            'ed25519_public_key' => env('WEBHOOK_ED25519_PUBLIC_KEY'),
            'webhook_profile' => ProcessEverything::class,
            'webhook_response' => DefaultResponse::class,
            'webhook_model' => WebhookCall::class,
            'process_webhook_job' => ProcessWebhookJob::class,
            'store_headers' => ['*'], // Or specific headers
            'delete_after_days' => 30,
            'timestamp_tolerance_seconds' => 300,
        ],
    ],
],
```

## Testing

The package includes comprehensive tests for both HMAC and Ed25519 flows:

```bash
composer test
```

## Security

This package implements Standard Webhooks security best practices:

1. **Signature Verification**: All webhooks are signed with HMAC-SHA256 or Ed25519
2. **Timestamp Validation**: Prevents replay attacks with configurable tolerance window
3. **Idempotency**: Uses webhook-id to prevent duplicate processing
4. **SSL/TLS**: Enforces secure connections by default
5. **Constant-Time Comparison**: Uses `hash_equals()` for signature verification

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/webhook/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/webhook.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/webhook.svg

[link-tests]: https://github.com/faustbrian/webhook/actions
[link-packagist]: https://packagist.org/packages/cline/webhook
[link-downloads]: https://packagist.org/packages/cline/webhook
[link-security]: https://github.com/faustbrian/webhook/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
