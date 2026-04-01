<?php

namespace Webkul\Admin\Services\Configuration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WhatsAppTestMessageSender
{
    /**
     * Send a test WhatsApp message using the active provider.
     */
    public function send(array $config, string $phone, string $message): array
    {
        $driver = $config['driver'] ?? null;

        try {
            $normalizedPhone = $this->normalizePhone($phone);

            return match ($driver) {
                'meta' => $this->sendViaMeta($config, $normalizedPhone, $message),
                'waha' => $this->sendViaWaha($config, $normalizedPhone, $message),
                'evolution' => $this->sendViaEvolution($config, $normalizedPhone, $message),
                default => [
                    'success' => false,
                    'message' => trans('admin::app.configuration.index.whatsapp.test-message.errors.invalid-driver'),
                ],
            };
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => trans('admin::app.configuration.index.whatsapp.test-message.errors.generic').' '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Send a message using Meta Cloud API.
     */
    protected function sendViaMeta(array $config, string $phone, string $message): array
    {
        $baseUrl = $this->normalizeBaseUrl($config['meta']['base_url'] ?? 'https://graph.facebook.com');
        $apiVersion = ltrim((string) ($config['meta']['api_version'] ?? 'v21.0'), '/');
        $accessToken = (string) ($config['meta']['access_token'] ?? '');
        $phoneNumberId = (string) ($config['meta']['phone_number_id'] ?? '');

        try {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout(20)
                ->post("{$baseUrl}/{$apiVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.meta-token'));
        }

        if (! $response->successful()) {
            $errorMessage = $this->extractErrorMessage($response);

            if ($this->containsAny($errorMessage, ['recipient', 'phone', 'wa_id', 'invalid parameter'])) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.invalid-phone'));
            }

            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.generic').' '.$errorMessage);
        }

        return [
            'success' => true,
            'message' => trans('admin::app.configuration.index.whatsapp.test-message.success.meta', ['phone' => $phone]),
        ];
    }

    /**
     * Send a message using WAHA.
     */
    protected function sendViaWaha(array $config, string $phone, string $message): array
    {
        $baseUrl = $this->normalizeBaseUrl($config['waha']['base_url'] ?? '');
        $apiKey = (string) ($config['waha']['api_key'] ?? '');
        $session = (string) ($config['waha']['session'] ?? '');
        $timeout = (int) ($config['waha']['timeout'] ?? 30);

        $client = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'X-Api-Key' => $apiKey,
                'Authorization' => 'Bearer '.$apiKey,
            ]);

        $this->assertWahaSessionAvailable($client, $baseUrl, $session);

        $chatId = $phone.'@c.us';
        $payloads = [
            [
                'session' => $session,
                'chatId' => $chatId,
                'text' => $message,
            ],
            [
                'session' => $session,
                'chatId' => $phone,
                'text' => $message,
            ],
        ];

        $endpoints = [
            "{$baseUrl}/api/sendText",
            "{$baseUrl}/api/messages/text",
            "{$baseUrl}/sendText",
        ];

        $lastResponse = null;

        foreach ($endpoints as $endpoint) {
            foreach ($payloads as $payload) {
                try {
                    $response = $client->post($endpoint, $payload);
                } catch (ConnectionException) {
                    throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.host'));
                }

                $lastResponse = $response;

                if ($this->isUnauthorized($response)) {
                    throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-api-key'));
                }

                if ($response->status() === 404) {
                    continue;
                }

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'message' => trans('admin::app.configuration.index.whatsapp.test-message.success.waha', ['phone' => $phone]),
                    ];
                }

                $errorMessage = strtolower($this->extractErrorMessage($response));

                if ($this->containsAny($errorMessage, ['session', 'not found', 'disconnected', 'offline'])) {
                    throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-session-disconnected'));
                }

                if ($this->containsAny($errorMessage, ['phone', 'chatid', 'invalid'])) {
                    throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.invalid-phone'));
                }
            }
        }

        if ($lastResponse && $lastResponse->status() === 404) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.generic'));
        }

        throw new RuntimeException(
            trans('admin::app.configuration.index.whatsapp.test-message.errors.generic').' '.($lastResponse ? $this->extractErrorMessage($lastResponse) : '')
        );
    }

    /**
     * Send a message using Evolution.
     */
    protected function sendViaEvolution(array $config, string $phone, string $message): array
    {
        $baseUrl = $this->normalizeBaseUrl($config['evolution']['base_url'] ?? '');
        $apiKey = (string) ($config['evolution']['api_key'] ?? '');
        $instance = (string) ($config['evolution']['instance'] ?? '');
        $timeout = (int) ($config['evolution']['timeout'] ?? 30);

        $client = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'apikey' => $apiKey,
                'x-api-key' => $apiKey,
                'Authorization' => 'Bearer '.$apiKey,
            ]);

        $this->assertEvolutionInstanceAvailable($client, $baseUrl, $instance);

        $requests = [
            [
                'url' => "{$baseUrl}/message/sendText/{$instance}",
                'payload' => [
                    'number' => $phone,
                    'textMessage' => [
                        'text' => $message,
                    ],
                ],
            ],
            [
                'url' => "{$baseUrl}/message/sendText/{$instance}",
                'payload' => [
                    'number' => $phone,
                    'text' => $message,
                ],
            ],
            [
                'url' => "{$baseUrl}/message/sendText",
                'payload' => [
                    'instance' => $instance,
                    'number' => $phone,
                    'text' => $message,
                ],
            ],
        ];

        $lastResponse = null;

        foreach ($requests as $request) {
            try {
                $response = $client->post($request['url'], $request['payload']);
            } catch (ConnectionException) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.host'));
            }

            $lastResponse = $response;

            if ($this->isUnauthorized($response)) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-api-key'));
            }

            if ($response->status() === 404) {
                continue;
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => trans('admin::app.configuration.index.whatsapp.test-message.success.evolution', ['phone' => $phone]),
                ];
            }

            $errorMessage = strtolower($this->extractErrorMessage($response));

            if ($this->containsAny($errorMessage, ['instance', 'not found', 'does not exist'])) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance'));
            }

            if ($this->containsAny($errorMessage, ['state', 'closed', 'disconnect', 'offline'])) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance-disconnected'));
            }

            if ($this->containsAny($errorMessage, ['phone', 'number', 'invalid'])) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.invalid-phone'));
            }
        }

        if ($lastResponse && $lastResponse->status() === 404) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance'));
        }

        throw new RuntimeException(
            trans('admin::app.configuration.index.whatsapp.test-message.errors.generic').' '.($lastResponse ? $this->extractErrorMessage($lastResponse) : '')
        );
    }

    /**
     * Verify WAHA session exists and appears available.
     */
    protected function assertWahaSessionAvailable($client, string $baseUrl, string $session): void
    {
        try {
            $response = $client->get("{$baseUrl}/api/sessions/{$session}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-api-key'));
        }

        if ($response->successful()) {
            $state = strtolower((string) data_get($response->json(), 'status', data_get($response->json(), 'state', '')));

            if ($state !== '' && $this->containsAny($state, ['disconnected', 'offline', 'failed'])) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-session-disconnected'));
            }

            return;
        }

        if ($response->status() === 404) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-session'));
        }

        throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.waha-session-disconnected'));
    }

    /**
     * Verify Evolution instance exists and is connected.
     */
    protected function assertEvolutionInstanceAvailable($client, string $baseUrl, string $instance): void
    {
        try {
            $response = $client->get("{$baseUrl}/instance/connectionState/{$instance}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-api-key'));
        }

        if ($response->status() === 404) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance'));
        }

        if (! $response->successful()) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance-disconnected'));
        }

        $state = strtolower((string) (data_get($response->json(), 'instance.state')
            ?? data_get($response->json(), 'state')
            ?? data_get($response->json(), 'instanceStatus')
            ?? ''));

        if ($state !== '' && ! $this->containsAny($state, ['open', 'connected'])) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.evolution-instance-disconnected'));
        }
    }

    /**
     * Normalize and sanitize phone before provider call.
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test-message.errors.invalid-phone'));
        }

        return $digits;
    }

    /**
     * Determine if response indicates auth failure.
     */
    protected function isUnauthorized(Response $response): bool
    {
        return in_array($response->status(), [401, 403], true);
    }

    /**
     * Normalize base url.
     */
    protected function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    /**
     * Extract meaningful API error message.
     */
    protected function extractErrorMessage(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            $possiblePaths = [
                'error.message',
                'message',
                'error',
                'response.message',
            ];

            foreach ($possiblePaths as $path) {
                $value = data_get($body, $path);

                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        $text = trim($response->body());

        return $text !== '' ? $text : "HTTP {$response->status()}";
    }

    /**
     * Check if haystack contains any needle.
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains(strtolower($haystack), strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
