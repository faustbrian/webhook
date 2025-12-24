<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

describe('Happy Paths', function (): void {
    test('allows request through when signature is valid', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->once()
            ->with(Mockery::type(Request::class), 'test-secret')
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'test-secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], '{"event":"test"}');
        $middleware = new VerifyWebhookSignature();
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('OK', Symfony\Component\HttpFoundation\Response::HTTP_OK);
        });

        // Assert
        expect($nextCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('OK');
    });

    test('passes request object to next middleware', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();
        $passedRequest = null;

        // Act
        $middleware->handle($request, function ($req) use (&$passedRequest): Response {
            $passedRequest = $req;

            return new Response('OK');
        });

        // Assert
        expect($passedRequest)->toBe($request);
    });

    test('uses default config when no config name provided', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'default-secret')
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'default-secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('uses custom config when config name provided', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'stripe-secret')
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.stripe.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.stripe.signing_secret', 'stripe-secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'), 'stripe');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('resolves validator from container using configured class', function (): void {
        // Arrange
        $customValidator = new class() implements SignatureValidator
        {
            public bool $wasUsed = false;

            public function verify(Request $request, string $secret): void {}

            public function isValid(Request $request, string $secret): bool
            {
                $this->wasUsed = true;

                return true;
            }
        };

        $validatorClass = $customValidator::class;
        app()->instance($validatorClass, $customValidator);
        Config::set('webhook.client.configs.default.signature_validator', $validatorClass);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($customValidator->wasUsed)->toBeTrue();
    });
});

describe('Sad Paths', function (): void {
    test('returns 401 when signature is invalid', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(401)
            ->and($response->getContent())->toBe('Invalid signature');
    });

    test('does not call next middleware when signature is invalid', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(false);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();
        $nextCalled = false;

        // Act
        $middleware->handle($request, function ($req) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('OK');
        });

        // Assert
        expect($nextCalled)->toBeFalse();
    });

    test('fires InvalidWebhookSignatureEvent when signature is invalid', function (): void {
        // Arrange
        Event::fake([InvalidWebhookSignatureEvent::class]);

        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(false);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        Event::assertDispatched(InvalidWebhookSignatureEvent::class, fn ($event): bool => $event->request === $request
            && $event->configName === 'default');
    });

    test('fires event with correct config name when using custom config', function (): void {
        // Arrange
        Event::fake([InvalidWebhookSignatureEvent::class]);

        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(false);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.stripe.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.stripe.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'), 'stripe');

        // Assert
        Event::assertDispatched(InvalidWebhookSignatureEvent::class, fn ($event): bool => $event->configName === 'stripe');
    });

    test('does not fire event when signature is valid', function (): void {
        // Arrange
        Event::fake([InvalidWebhookSignatureEvent::class]);

        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        Event::assertNotDispatched(InvalidWebhookSignatureEvent::class);
    });
});

describe('Edge Cases', function (): void {
    test('handles request with empty body', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'secret')
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], '');
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles request with large payload', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $largePayload = json_encode([
            'data' => array_fill(0, 1_000, [
                'id' => fake()->uuid(),
                'name' => fake()->name(),
            ]),
        ]);

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $largePayload);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles request with unicode characters in payload', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $unicodePayload = json_encode([
            'message' => '你好世界 🌍 Здравствуй мир',
            'emoji' => '🎉🎊🎈',
        ]);

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], $unicodePayload);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles request with special characters in headers', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST, [], [], [], [], '{"test":"data"}');
        $request->headers->set('X-Custom-Header', 'value with spaces & special <chars>');

        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('handles multiple concurrent validation calls with different configs', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'secret1')
            ->once()
            ->andReturn(true);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'secret2')
            ->once()
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.config1.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.config1.signing_secret', 'secret1');
        Config::set('webhook.client.configs.config2.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.config2.signing_secret', 'secret2');

        $request1 = Request::create('/webhook1', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request2 = Request::create('/webhook2', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware1 = new VerifyWebhookSignature();
        $middleware2 = new VerifyWebhookSignature();

        // Act
        $response1 = $middleware1->handle($request1, fn ($req): Response => new Response('OK1'), 'config1');
        $response2 = $middleware2->handle($request2, fn ($req): Response => new Response('OK2'), 'config2');

        // Assert
        expect($response1->getStatusCode())->toBe(200)
            ->and($response2->getStatusCode())->toBe(200)
            ->and($response1->getContent())->toBe('OK1')
            ->and($response2->getContent())->toBe('OK2');
    });

    test('handles config name with special characters', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.my-custom_config.123.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.my-custom_config.123.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'), 'my-custom_config.123');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('validator receives correct secret from config', function (): void {
        // Arrange
        $expectedSecret = 'very-secure-secret-key-12345';
        $receivedSecret = null;

        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->once()
            ->with(Mockery::type(Request::class), $expectedSecret)
            ->andReturnUsing(function ($request, $secret) use (&$receivedSecret): true {
                $receivedSecret = $secret;

                return true;
            });

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', $expectedSecret);

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'));

        // Assert
        expect($receivedSecret)->toBe($expectedSecret);
    });
});

describe('Middleware Chain Integration', function (): void {
    test('can be stacked with other middleware', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware1 = new VerifyWebhookSignature();
        $middleware2Order = [];

        // Act
        $response = $middleware1->handle($request, function ($req) use (&$middleware2Order): Response {
            $middleware2Order[] = 'middleware2';

            return new Response('OK');
        });

        $middleware2Order[] = 'after';

        // Assert
        expect($middleware2Order)->toBe(['middleware2', 'after'])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('stops middleware chain on invalid signature', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(false);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware1 = new VerifyWebhookSignature();
        $subsequentMiddlewareCalled = false;

        // Act
        $response = $middleware1->handle($request, function ($req) use (&$subsequentMiddlewareCalled): Response {
            $subsequentMiddlewareCalled = true;

            return new Response('Should not reach');
        });

        // Assert
        expect($subsequentMiddlewareCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(401);
    });

    test('preserves request modifications from previous middleware', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->attributes->set('modified_by_previous_middleware', true);

        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, function ($req) use (&$capturedRequest): Response {
            $capturedRequest = $req;

            return new Response('OK');
        });

        // Assert
        expect($capturedRequest->attributes->get('modified_by_previous_middleware'))->toBeTrue();
    });

    test('allows subsequent middleware to modify response', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, function ($req): Response {
            $response = new Response('Modified Content', Symfony\Component\HttpFoundation\Response::HTTP_OK);
            $response->header('X-Custom-Header', 'custom-value');

            return $response;
        });

        // Assert
        expect($response->getContent())->toBe('Modified Content')
            ->and($response->headers->get('X-Custom-Header'))->toBe('custom-value');
    });
});

describe('Configuration Resolution', function (): void {
    test('retrieves signing secret from correct config path', function (): void {
        // Arrange
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->with(Mockery::type(Request::class), 'github-webhook-secret')
            ->andReturn(true);

        app()->instance(SignatureValidator::class, $validator);
        Config::set('webhook.client.configs.github.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.github.signing_secret', 'github-webhook-secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $response = $middleware->handle($request, fn ($req): Response => new Response('OK'), 'github');

        // Assert
        expect($response->getStatusCode())->toBe(200);
    });

    test('retrieves validator class from correct config path', function (): void {
        // Arrange
        $customValidator = new class() implements SignatureValidator
        {
            public static bool $resolved = false;

            public function verify(Request $request, string $secret): void {}

            public function isValid(Request $request, string $secret): bool
            {
                self::$resolved = true;

                return true;
            }
        };

        $validatorClass = $customValidator::class;
        app()->instance($validatorClass, $customValidator);
        Config::set('webhook.client.configs.shopify.signature_validator', $validatorClass);
        Config::set('webhook.client.configs.shopify.signing_secret', 'secret');

        $request = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request, fn ($req): Response => new Response('OK'), 'shopify');

        // Assert
        expect($customValidator::$resolved)->toBeTrue();
    });

    test('resolves new validator instance for each request', function (): void {
        // Arrange
        $callCount = 0;
        $validator = Mockery::mock(SignatureValidator::class);
        $validator->shouldReceive('isValid')
            ->twice()
            ->andReturnUsing(function () use (&$callCount): true {
                ++$callCount;

                return true;
            });

        app()->bind(SignatureValidator::class, fn () => $validator);
        Config::set('webhook.client.configs.default.signature_validator', SignatureValidator::class);
        Config::set('webhook.client.configs.default.signing_secret', 'secret');

        $request1 = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request2 = Request::create('/webhook', Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $middleware = new VerifyWebhookSignature();

        // Act
        $middleware->handle($request1, fn ($req): Response => new Response('OK'));
        $middleware->handle($request2, fn ($req): Response => new Response('OK'));

        // Assert
        expect($callCount)->toBe(2);
    });
});

afterEach(function (): void {
    Mockery::close();
});
