<?php

namespace Webkul\Admin\Services\Configuration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WhatsAppConnectionTester
{
    /**
     * Test the configured WhatsApp provider connection.
     */
    public function test(array $config): array
    {
        $driver = $config['driver'] ?? null;

        try {
            return match ($driver) {
                'meta' => $this->testMetaConnection($config),
                'waha' => $this->testWahaConnection($config),
                'evolution' => $this->testEvolutionConnection($config),
                default => [
                    'success' => false,
                    'message' => trans('admin::app.configuration.index.whatsapp.test.errors.invalid-driver'),
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
                'message' => trans('admin::app.configuration.index.whatsapp.test.errors.generic').' '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Validate Meta Official credentials.
     */
    protected function testMetaConnection(array $config): array
    {
        $baseUrl = $this->normalizeBaseUrl($config['meta']['base_url'] ?? 'https://graph.facebook.com');
        $apiVersion = ltrim((string) ($config['meta']['api_version'] ?? 'v21.0'), '/');
        $accessToken = (string) ($config['meta']['access_token'] ?? '');
        $businessAccountId = (string) ($config['meta']['business_account_id'] ?? '');
        $phoneNumberId = (string) ($config['meta']['phone_number_id'] ?? '');

        try {
            $phoneResponse = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout(20)
                ->get("{$baseUrl}/{$apiVersion}/{$phoneNumberId}", [
                    'fields' => 'id,display_phone_number,verified_name',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
        }

        if ($this->isUnauthorized($phoneResponse)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.meta-token'));
        }

        if (! $phoneResponse->successful()) {
            throw new RuntimeException(
                trans('admin::app.configuration.index.whatsapp.test.errors.meta-phone-id').' '.$this->extractErrorMessage($phoneResponse)
            );
        }

        try {
            $businessResponse = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout(20)
                ->get("{$baseUrl}/{$apiVersion}/{$businessAccountId}", [
                    'fields' => 'id,name',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
        }

        if ($this->isUnauthorized($businessResponse)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.meta-token'));
        }

        if (! $businessResponse->successful()) {
            throw new RuntimeException(
                trans('admin::app.configuration.index.whatsapp.test.errors.meta-business-id').' '.$this->extractErrorMessage($businessResponse)
            );
        }

        return [
            'success' => true,
            'message' => trans('admin::app.configuration.index.whatsapp.test.success.meta'),
        ];
    }

    /**
     * Validate WAHA credentials and configured session.
     */
    protected function testWahaConnection(array $config): array
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

        try {
            $sessionResponse = $client->get("{$baseUrl}/api/sessions/{$session}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
        }

        if ($this->isUnauthorized($sessionResponse)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.waha-api-key'));
        }

        if ($sessionResponse->successful()) {
            return [
                'success' => true,
                'message' => trans('admin::app.configuration.index.whatsapp.test.success.waha'),
            ];
        }

        if ($sessionResponse->status() === 404) {
            try {
                $listResponse = $client->get("{$baseUrl}/api/sessions");
            } catch (ConnectionException) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
            }

            if ($this->isUnauthorized($listResponse)) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.waha-api-key'));
            }

            if ($listResponse->successful()) {
                if (! $this->arrayContainsValue($listResponse->json(), $session)) {
                    throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.waha-session'));
                }

                return [
                    'success' => true,
                    'message' => trans('admin::app.configuration.index.whatsapp.test.success.waha'),
                ];
            }

            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.waha-session'));
        }

        throw new RuntimeException(
            trans('admin::app.configuration.index.whatsapp.test.errors.generic').' '.$this->extractErrorMessage($sessionResponse)
        );
    }

    /**
     * Validate Evolution credentials and instance.
     */
    protected function testEvolutionConnection(array $config): array
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

        try {
            $stateResponse = $client->get("{$baseUrl}/instance/connectionState/{$instance}");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
        }

        if ($this->isUnauthorized($stateResponse)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.evolution-api-key'));
        }

        if ($stateResponse->successful()) {
            return [
                'success' => true,
                'message' => trans('admin::app.configuration.index.whatsapp.test.success.evolution'),
            ];
        }

        try {
            $instancesResponse = $client->get("{$baseUrl}/instance/fetchInstances");
        } catch (ConnectionException) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.host'));
        }

        if ($this->isUnauthorized($instancesResponse)) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.evolution-api-key'));
        }

        if ($instancesResponse->successful()) {
            if (! $this->arrayContainsValue($instancesResponse->json(), $instance)) {
                throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.evolution-instance'));
            }

            return [
                'success' => true,
                'message' => trans('admin::app.configuration.index.whatsapp.test.success.evolution'),
            ];
        }

        if ($stateResponse->status() === 404 || $instancesResponse->status() === 404) {
            throw new RuntimeException(trans('admin::app.configuration.index.whatsapp.test.errors.evolution-instance'));
        }

        throw new RuntimeException(
            trans('admin::app.configuration.index.whatsapp.test.errors.generic').' '.$this->extractErrorMessage($instancesResponse)
        );
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
     * Recursively find value inside JSON-like arrays.
     */
    protected function arrayContainsValue(mixed $data, string $needle): bool
    {
        if (is_string($data)) {
            return str_contains(strtolower($data), strtolower($needle));
        }

        if (! is_array($data)) {
            return false;
        }

        foreach ($data as $value) {
            if ($this->arrayContainsValue($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
