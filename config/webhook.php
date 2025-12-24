<?php

declare(strict_types=1);

use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Client\Profiles\ProcessEverything;
use Cline\Webhook\Client\Responses\DefaultResponse;
use Cline\Webhook\Client\Validators\HmacValidator;
use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for receiving and processing webhooks from external sources.
    | You can define multiple webhook endpoints with different configurations.
    |
    */
    'client' => [
        'configs' => [
            'default' => [
                /*
                |--------------------------------------------------------------------------
                | Signing Secret
                |--------------------------------------------------------------------------
                |
                | The secret used to verify webhook signatures (HMAC-SHA256).
                | For Ed25519, use ed25519_public_key instead.
                |
                */
                'signing_secret' => env('WEBHOOK_SECRET'),

                /*
                |--------------------------------------------------------------------------
                | Signature Validator
                |--------------------------------------------------------------------------
                |
                | The class responsible for verifying webhook signatures.
                | Options: HmacValidator::class, Ed25519Validator::class
                |
                */
                'signature_validator' => HmacValidator::class,

                /*
                |--------------------------------------------------------------------------
                | Ed25519 Public Key
                |--------------------------------------------------------------------------
                |
                | Base64-encoded Ed25519 public key for signature verification.
                | Only required when using Ed25519Validator.
                |
                */
                'ed25519_public_key' => env('WEBHOOK_ED25519_PUBLIC_KEY'),

                /*
                |--------------------------------------------------------------------------
                | Webhook Profile
                |--------------------------------------------------------------------------
                |
                | Determines which webhooks should be processed.
                | Implement WebhookProfile interface for custom filtering.
                |
                */
                'webhook_profile' => ProcessEverything::class,

                /*
                |--------------------------------------------------------------------------
                | Webhook Response
                |--------------------------------------------------------------------------
                |
                | Defines the HTTP response returned to webhook sender.
                | Implement WebhookResponse interface for custom responses.
                |
                */
                'webhook_response' => DefaultResponse::class,

                /*
                |--------------------------------------------------------------------------
                | Webhook Model
                |--------------------------------------------------------------------------
                |
                | The Eloquent model used to store webhook calls.
                | Extend WebhookCall for custom behavior.
                |
                */
                'webhook_model' => WebhookCall::class,

                /*
                |--------------------------------------------------------------------------
                | Process Webhook Job
                |--------------------------------------------------------------------------
                |
                | The job class responsible for processing webhooks asynchronously.
                |
                */
                'process_webhook_job' => ProcessWebhookJob::class,

                /*
                |--------------------------------------------------------------------------
                | Store Headers
                |--------------------------------------------------------------------------
                |
                | Which HTTP headers to store in database.
                | Use ['*'] to store all headers or specify individual headers.
                |
                */
                'store_headers' => ['*'],

                /*
                |--------------------------------------------------------------------------
                | Automatic Pruning
                |--------------------------------------------------------------------------
                |
                | Number of days after which webhook records are automatically pruned.
                | Set to null to disable automatic pruning.
                |
                */
                'delete_after_days' => 30,

                /*
                |--------------------------------------------------------------------------
                | Timestamp Tolerance
                |--------------------------------------------------------------------------
                |
                | Maximum age in seconds for webhook timestamps (replay attack prevention).
                | Standard Webhooks recommends 5 minutes (300 seconds).
                |
                */
                'timestamp_tolerance_seconds' => 300,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for dispatching webhooks to external endpoints.
    |
    */
    'server' => [
        /*
        |--------------------------------------------------------------------------
        | Queue Connection
        |--------------------------------------------------------------------------
        |
        | The queue connection to use for dispatching webhooks asynchronously.
        | Set to null to use the default queue connection.
        |
        */
        'queue' => env('WEBHOOK_QUEUE'),

        /*
        |--------------------------------------------------------------------------
        | HTTP Verb
        |--------------------------------------------------------------------------
        |
        | The HTTP method used for webhook requests.
        | Standard Webhooks uses POST.
        |
        */
        'http_verb' => 'POST',

        /*
        |--------------------------------------------------------------------------
        | Request Timeout
        |--------------------------------------------------------------------------
        |
        | Maximum time in seconds to wait for webhook response.
        |
        */
        'timeout_in_seconds' => 3,

        /*
        |--------------------------------------------------------------------------
        | Maximum Retry Attempts
        |--------------------------------------------------------------------------
        |
        | Number of times to retry failed webhook calls.
        |
        */
        'tries' => 3,

        /*
        |--------------------------------------------------------------------------
        | Backoff Strategy
        |--------------------------------------------------------------------------
        |
        | Strategy for calculating retry delays.
        | Implement BackoffStrategy interface for custom strategies.
        |
        */
        'backoff_strategy' => ExponentialBackoffStrategy::class,

        /*
        |--------------------------------------------------------------------------
        | SSL Verification
        |--------------------------------------------------------------------------
        |
        | Whether to verify SSL certificates for HTTPS requests.
        | Set to false only for development/testing.
        |
        */
        'verify_ssl' => true,

        /*
        |--------------------------------------------------------------------------
        | Throw Exception on Failure
        |--------------------------------------------------------------------------
        |
        | Whether to throw exception when all retry attempts are exhausted.
        | If false, failures are silent (logged via events).
        |
        */
        'throw_exception_on_failure' => false,

        /*
        |--------------------------------------------------------------------------
        | Signature Version
        |--------------------------------------------------------------------------
        |
        | Default signature version for webhook signing.
        | Options: 'v1' (HMAC-SHA256), 'v1a' (Ed25519)
        |
        */
        'signature_version' => SignatureVersion::V1_HMAC->value,

        /*
        |--------------------------------------------------------------------------
        | Signing Secret
        |--------------------------------------------------------------------------
        |
        | Secret key for HMAC-SHA256 signatures.
        | Only required when using v1 signatures.
        |
        */
        'signing_secret' => env('WEBHOOK_SIGNING_SECRET'),

        /*
        |--------------------------------------------------------------------------
        | Ed25519 Private Key
        |--------------------------------------------------------------------------
        |
        | Base64-encoded Ed25519 private key for signing.
        | Only required when using v1a signatures.
        |
        */
        'ed25519_private_key' => env('WEBHOOK_ED25519_PRIVATE_KEY'),
    ],
];
