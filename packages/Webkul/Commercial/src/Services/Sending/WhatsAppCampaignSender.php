<?php

namespace Webkul\Commercial\Services\Sending;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Webkul\Commercial\Models\CommercialCampaignDelivery;

class WhatsAppCampaignSender implements CampaignChannelSenderInterface
{
    public function send(CommercialCampaignDelivery $delivery): bool
    {
        if (empty($delivery->recipient_phone)) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.invalid-phone'));
        }

        $message = trim((string) ($delivery->rendered_message ?? ''));

        if ($message === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.empty-message'));
        }

        $phone = $this->normalizePhone($delivery->recipient_phone);
        $provider = $this->normalizeProvider((string) ($delivery->provider ?: ''));
        $config = $this->getProviderConfiguration($provider);

        $providerMessageId = match ($provider) {
            'meta_official' => $this->sendViaMetaOfficial($config, $phone, $message),
            'waha' => $this->sendViaWaha($config, $phone, $message),
            'evolution' => $this->sendViaEvolution($config, $phone, $message),
            default => throw new RuntimeException(
                trans('admin::app.commercial.campaigns.deliveries.errors.unsupported-provider', ['provider' => $provider])
            ),
        };

        if ($providerMessageId !== null) {
            $delivery->update([
                'provider_message_id' => mb_substr((string) $providerMessageId, 0, 255),
            ]);
        }

        return true;
    }

    public function supports(string $channel, ?string $provider = null): bool
    {
        return $channel === 'whatsapp';
    }

    protected function sendViaMetaOfficial(array $config, string $phone, string $message): ?string
    {
        $baseUrl = $this->normalizeBaseUrl((string) ($config['base_url'] ?? 'https://graph.facebook.com'));
        $apiVersion = ltrim((string) ($config['api_version'] ?? 'v21.0'), '/');
        $accessToken = (string) ($config['access_token'] ?? '');
        $phoneNumberId = (string) ($config['phone_number_id'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.meta-token'));
        }

        if ($phoneNumberId === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.meta-phone-id'));
        }

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
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.meta-token'));
        }

        if (! $response->successful()) {
            $errorMessage = strtolower($this->extractErrorMessage($response));

            if ($this->containsAny($errorMessage, ['phone_number_id', 'phone number id'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.meta-phone-id'));
            }

            if ($this->containsAny($errorMessage, ['recipient', 'phone', 'wa_id', 'invalid parameter'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.invalid-phone'));
            }

            throw new RuntimeException(
                trans('admin::app.commercial.campaigns.deliveries.errors.generic').' '.$this->extractErrorMessage($response)
            );
        }

        return data_get($response->json(), 'messages.0.id');
    }

    protected function sendViaWaha(array $config, string $phone, string $message): ?string
    {
        $baseUrl = $this->normalizeBaseUrl((string) ($config['base_url'] ?? ''));
        $apiKey = (string) ($config['api_key'] ?? '');
        $session = (string) ($config['session'] ?? '');
        $timeout = (int) ($config['timeout'] ?? 30);

        if ($baseUrl === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
        }

        if ($apiKey === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-api-key'));
        }

        if ($session === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session'));
        }

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
                    throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
                }

                $lastResponse = $response;

                if ($this->isUnauthorized($response)) {
                    throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-api-key'));
                }

                if ($response->status() === 404) {
                    continue;
                }

                if ($response->successful()) {
                    return data_get($response->json(), 'id')
                        ?? data_get($response->json(), 'messageId')
                        ?? data_get($response->json(), 'data.id');
                }

                $errorMessage = strtolower($this->extractErrorMessage($response));

                if ($this->containsAny($errorMessage, ['session', 'not found', 'disconnected', 'offline'])) {
                    throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session-disconnected'));
                }

                if ($this->containsAny($errorMessage, ['phone', 'chatid', 'invalid'])) {
                    throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.invalid-phone'));
                }
            }
        }

        if ($lastResponse && $lastResponse->status() === 404) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session'));
        }

        throw new RuntimeException(
            trans('admin::app.commercial.campaigns.deliveries.errors.generic')
            .' '.($lastResponse ? $this->extractErrorMessage($lastResponse) : '')
        );
    }

    protected function sendViaEvolution(array $config, string $phone, string $message): ?string
    {
        $baseUrl = $this->normalizeBaseUrl((string) ($config['base_url'] ?? ''));
        $apiKey = (string) ($config['api_key'] ?? '');
        $instance = (string) ($config['instance'] ?? '');
        $timeout = (int) ($config['timeout'] ?? 30);

        if ($baseUrl === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
        }

        if ($apiKey === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-api-key'));
        }

        if ($instance === '') {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance'));
        }

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
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
            }

            $lastResponse = $response;

            if ($this->isUnauthorized($response)) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-api-key'));
            }

            if ($response->status() === 404) {
                continue;
            }

            if ($response->successful()) {
                return data_get($response->json(), 'key.id')
                    ?? data_get($response->json(), 'id')
                    ?? data_get($response->json(), 'messageId')
                    ?? data_get($response->json(), 'data.id');
            }

            $errorMessage = strtolower($this->extractErrorMessage($response));

            if ($this->containsAny($errorMessage, ['instance', 'not found', 'does not exist'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance'));
            }

            if ($this->containsAny($errorMessage, ['state', 'closed', 'disconnect', 'offline'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance-disconnected'));
            }

            if ($this->containsAny($errorMessage, ['phone', 'number', 'invalid'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.invalid-phone'));
            }
        }

        if ($lastResponse && $lastResponse->status() === 404) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance'));
        }

        throw new RuntimeException(
            trans('admin::app.commercial.campaigns.deliveries.errors.generic')
            .' '.($lastResponse ? $this->extractErrorMessage($lastResponse) : '')
        );
    }

    protected function getProviderConfiguration(string $provider): array
    {
        return match ($provider) {
            'meta_official' => [
                'base_url' => $this->getConfigData('whatsapp.meta.base_url', 'https://graph.facebook.com'),
                'api_version' => $this->getConfigData('whatsapp.meta.api_version', 'v21.0'),
                'access_token' => $this->getConfigData('whatsapp.meta.access_token'),
                'phone_number_id' => $this->getConfigData('whatsapp.meta.phone_number_id'),
            ],
            'waha' => [
                'base_url' => $this->getConfigData('whatsapp.waha.base_url'),
                'api_key' => $this->getConfigData('whatsapp.waha.api_key'),
                'session' => $this->getConfigData('whatsapp.waha.session'),
                'timeout' => (int) ($this->getConfigData('whatsapp.waha.timeout', 30) ?: 30),
            ],
            'evolution' => [
                'base_url' => $this->getConfigData('whatsapp.evolution.base_url'),
                'api_key' => $this->getConfigData('whatsapp.evolution.api_key'),
                'instance' => $this->getConfigData('whatsapp.evolution.instance'),
                'timeout' => (int) ($this->getConfigData('whatsapp.evolution.timeout', 30) ?: 30),
            ],
            default => [],
        };
    }

    protected function assertWahaSessionAvailable($client, string $baseUrl, string $session): void
    {
        try {
            $response = $client->get("{$baseUrl}/api/sessions/{$session}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-api-key'));
        }

        if ($response->successful()) {
            $state = strtolower((string) data_get($response->json(), 'status', data_get($response->json(), 'state', '')));

            if ($state !== '' && $this->containsAny($state, ['disconnected', 'offline', 'failed'])) {
                throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session-disconnected'));
            }

            return;
        }

        if ($response->status() === 404) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session'));
        }

        throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.waha-session-disconnected'));
    }

    protected function assertEvolutionInstanceAvailable($client, string $baseUrl, string $instance): void
    {
        try {
            $response = $client->get("{$baseUrl}/instance/connectionState/{$instance}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.host'));
        }

        if ($this->isUnauthorized($response)) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-api-key'));
        }

        if ($response->status() === 404) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance'));
        }

        if (! $response->successful()) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance-disconnected'));
        }

        $state = strtolower((string) (data_get($response->json(), 'instance.state')
            ?? data_get($response->json(), 'state')
            ?? data_get($response->json(), 'instanceStatus')
            ?? ''));

        if ($state !== '' && ! $this->containsAny($state, ['open', 'connected'])) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.evolution-instance-disconnected'));
        }
    }

    protected function normalizeProvider(string $provider): string
    {
        return match ($provider) {
            'meta', 'meta_official' => 'meta_official',
            'waha' => 'waha',
            'evolution' => 'evolution',
            default => match ((string) ($this->getConfigData('whatsapp.provider.driver', 'waha') ?: 'waha')) {
                'meta' => 'meta_official',
                'evolution' => 'evolution',
                default => 'waha',
            },
        };
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new RuntimeException(trans('admin::app.commercial.campaigns.deliveries.errors.invalid-phone'));
        }

        return $digits;
    }

    protected function isUnauthorized(Response $response): bool
    {
        return in_array($response->status(), [401, 403], true);
    }

    protected function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    protected function extractErrorMessage(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            foreach (['error.message', 'message', 'error', 'response.message'] as $path) {
                $value = data_get($body, $path);

                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        $text = trim($response->body());

        return $text !== '' ? $text : "HTTP {$response->status()}";
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        $haystack = strtolower($haystack);

        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    protected function getConfigData(string $path, mixed $default = null): mixed
    {
        try {
            return core()->getConfigData($path) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
