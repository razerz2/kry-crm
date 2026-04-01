<?php

namespace Webkul\Admin\Http\Controllers\Configuration;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\ConfigurationForm;
use Webkul\Admin\Services\Configuration\WhatsAppConnectionTester;
use Webkul\Admin\Services\Configuration\WhatsAppTestMessageSender;
use Webkul\Core\Repositories\CoreConfigRepository as ConfigurationRepository;

class ConfigurationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ConfigurationRepository $configurationRepository,
        protected WhatsAppConnectionTester $whatsAppConnectionTester,
        protected WhatsAppTestMessageSender $whatsAppTestMessageSender,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        if (
            request()->route('slug')
            && request()->route('slug2')
        ) {
            return view('admin::configuration.edit');
        }

        if (request()->route('slug')) {
            $activeConfiguration = system_config()->getActiveConfigurationItem();

            if (
                $activeConfiguration
                && (
                    $activeConfiguration->haveChildren()
                    || $activeConfiguration->getFields()->isNotEmpty()
                )
            ) {
                return view('admin::configuration.edit');
            }
        }

        return view('admin::configuration.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigurationForm $request): RedirectResponse
    {
        Event::dispatch('core.configuration.save.before');

        $this->configurationRepository->create($request->all());

        Event::dispatch('core.configuration.save.after');

        session()->flash('success', trans('admin::app.configuration.index.save-success'));

        return redirect()->back();
    }

    /**
     * Test SMTP configuration by sending a real email.
     */
    public function testSmtp(Request $request): JsonResponse
    {
        $smtpConfig = $this->getSmtpConfiguration($request);

        $validator = Validator::make(
            array_merge($smtpConfig, [
                'test_email' => $request->input('test_email'),
            ]),
            [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'min:1', 'max:65535'],
                'encryption' => ['required', 'in:tls,ssl,null'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:255'],
                'from_name' => ['required', 'string', 'max:255'],
                'from_address' => ['required', 'email', 'max:255'],
                'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
                'test_email' => ['required', 'email', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.email.smtp.test.validation-error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mailManager = app('mail.manager');

            $mailManager->build($this->buildSmtpMailerConfig($smtpConfig))
                ->raw(
                    trans('admin::app.configuration.index.email.smtp.test.body', [
                        'app_name' => config('app.name'),
                        'datetime' => now()->format('Y-m-d H:i:s'),
                    ]),
                    function ($message) use ($request) {
                        $message->to($request->input('test_email'))
                            ->subject(
                                trans('admin::app.configuration.index.email.smtp.test.subject', [
                                    'app_name' => config('app.name'),
                                ])
                            );
                    }
                );

            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.email.smtp.test.success', [
                    'email' => $request->input('test_email'),
                ]),
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'message' => $this->resolveSmtpErrorMessage($exception),
            ], 422);
        }
    }

    /**
     * Test WhatsApp provider configuration by active driver.
     */
    public function testWhatsApp(Request $request): JsonResponse
    {
        $whatsAppConfig = $this->getWhatsAppConfiguration($request);

        $validator = Validator::make(
            $this->flattenWhatsAppConfiguration($whatsAppConfig),
            $this->getWhatsAppValidationRules($whatsAppConfig['driver'] ?? null)
        );

        if ($validator->fails()) {
            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.whatsapp.test.validation-error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->whatsAppConnectionTester->test($whatsAppConfig);

        return new JsonResponse([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Send a real WhatsApp test message using the active provider.
     */
    public function testWhatsAppMessage(Request $request): JsonResponse
    {
        $whatsAppConfig = $this->getWhatsAppConfiguration($request);

        $validator = Validator::make(
            array_merge(
                $this->flattenWhatsAppConfiguration($whatsAppConfig),
                [
                    'phone' => $request->input('phone'),
                    'message' => $request->input('message'),
                ]
            ),
            array_merge(
                $this->getWhatsAppValidationRules($whatsAppConfig['driver'] ?? null),
                [
                    'phone' => ['required', 'string', 'max:30'],
                    'message' => ['required', 'string', 'max:1000'],
                ]
            )
        );

        if ($validator->fails()) {
            return new JsonResponse([
                'message' => trans('admin::app.configuration.index.whatsapp.test-message.validation-error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->whatsAppTestMessageSender->send(
            $whatsAppConfig,
            (string) $request->input('phone'),
            (string) $request->input('message')
        );

        return new JsonResponse([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * download the file for the specified resource.
     *
     * @return Response
     */
    public function download()
    {
        $path = request()->route()->parameters()['path'];

        $fileName = 'configuration/'.$path;

        $config = $this->configurationRepository->findOneByField('value', $fileName);

        return Storage::download($config['value']);
    }

    /**
     * Search for configurations.
     */
    public function search(): JsonResponse
    {
        $results = $this->configurationRepository->search(
            system_config()->getItems(),
            request()->query('query')
        );

        return new JsonResponse([
            'data' => $results,
        ]);
    }

    /**
     * Get SMTP config from request or persisted configuration.
     */
    protected function getSmtpConfiguration(Request $request): array
    {
        return [
            'host' => $request->input('email.smtp.host', core()->getConfigData('email.smtp.host')),
            'port' => $request->input('email.smtp.port', core()->getConfigData('email.smtp.port')),
            'encryption' => $request->input('email.smtp.encryption', core()->getConfigData('email.smtp.encryption') ?: 'null'),
            'username' => $request->input('email.smtp.username', core()->getConfigData('email.smtp.username')),
            'password' => $request->input('email.smtp.password', core()->getConfigData('email.smtp.password')),
            'from_name' => $request->input('email.smtp.from_name', core()->getConfigData('email.smtp.from_name')),
            'from_address' => $request->input('email.smtp.from_address', core()->getConfigData('email.smtp.from_address')),
            'timeout' => $request->input('email.smtp.timeout', core()->getConfigData('email.smtp.timeout')),
        ];
    }

    /**
     * Build Laravel dynamic SMTP mailer configuration.
     */
    protected function buildSmtpMailerConfig(array $smtpConfig): array
    {
        $timeout = $smtpConfig['timeout'];

        if ($timeout === '') {
            $timeout = null;
        }

        $encryption = $smtpConfig['encryption'];

        if ($encryption === 'null') {
            $encryption = null;
        }

        return [
            'transport' => 'smtp',
            'host' => $smtpConfig['host'],
            'port' => (int) $smtpConfig['port'],
            'encryption' => $encryption,
            'username' => $smtpConfig['username'] ?: null,
            'password' => $smtpConfig['password'] ?: null,
            'timeout' => $timeout !== null ? (int) $timeout : null,
            'from' => [
                'address' => $smtpConfig['from_address'],
                'name' => $smtpConfig['from_name'],
            ],
        ];
    }

    /**
     * Resolve a user-friendly SMTP error.
     */
    protected function resolveSmtpErrorMessage(Throwable $exception): string
    {
        $error = strtolower($exception->getMessage());

        $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.generic');

        if (
            str_contains($error, 'auth')
            || str_contains($error, '535')
            || str_contains($error, 'username and password not accepted')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.authentication');
        } elseif (
            str_contains($error, 'getaddrinfo')
            || str_contains($error, 'name or service not known')
            || str_contains($error, 'php_network_getaddresses')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.host');
        } elseif (
            str_contains($error, 'connection refused')
            || str_contains($error, 'failed to connect')
            || str_contains($error, 'connection could not be established')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.port');
        } elseif (
            str_contains($error, 'timed out')
            || str_contains($error, 'timeout')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.timeout');
        } elseif (
            str_contains($error, 'tls')
            || str_contains($error, 'ssl')
            || str_contains($error, 'certificate')
            || str_contains($error, 'starttls')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.tls');
        } elseif (
            str_contains($error, 'sender address rejected')
            || str_contains($error, 'from address')
            || str_contains($error, 'mail from')
            || str_contains($error, 'mailbox unavailable')
        ) {
            $prefix = trans('admin::app.configuration.index.email.smtp.test.errors.from-address');
        }

        return $prefix.' '.$exception->getMessage();
    }

    /**
     * Get WhatsApp config from request or persisted configuration.
     */
    protected function getWhatsAppConfiguration(Request $request): array
    {
        return [
            'driver' => $request->input('whatsapp.provider.driver', core()->getConfigData('whatsapp.provider.driver') ?: 'waha'),

            'meta' => [
                'base_url' => $request->input('whatsapp.meta.base_url', core()->getConfigData('whatsapp.meta.base_url') ?: 'https://graph.facebook.com'),
                'api_version' => $request->input('whatsapp.meta.api_version', core()->getConfigData('whatsapp.meta.api_version') ?: 'v21.0'),
                'access_token' => $request->input('whatsapp.meta.access_token', core()->getConfigData('whatsapp.meta.access_token')),
                'business_account_id' => $request->input('whatsapp.meta.business_account_id', core()->getConfigData('whatsapp.meta.business_account_id')),
                'phone_number_id' => $request->input('whatsapp.meta.phone_number_id', core()->getConfigData('whatsapp.meta.phone_number_id')),
                'webhook_verify_token' => $request->input('whatsapp.meta.webhook_verify_token', core()->getConfigData('whatsapp.meta.webhook_verify_token')),
            ],

            'waha' => [
                'base_url' => $request->input('whatsapp.waha.base_url', core()->getConfigData('whatsapp.waha.base_url')),
                'api_key' => $request->input('whatsapp.waha.api_key', core()->getConfigData('whatsapp.waha.api_key')),
                'session' => $request->input('whatsapp.waha.session', core()->getConfigData('whatsapp.waha.session')),
                'timeout' => $request->input('whatsapp.waha.timeout', core()->getConfigData('whatsapp.waha.timeout') ?: 30),
            ],

            'evolution' => [
                'base_url' => $request->input('whatsapp.evolution.base_url', core()->getConfigData('whatsapp.evolution.base_url')),
                'api_key' => $request->input('whatsapp.evolution.api_key', core()->getConfigData('whatsapp.evolution.api_key')),
                'instance' => $request->input('whatsapp.evolution.instance', core()->getConfigData('whatsapp.evolution.instance')),
                'timeout' => $request->input('whatsapp.evolution.timeout', core()->getConfigData('whatsapp.evolution.timeout') ?: 30),
            ],
        ];
    }

    /**
     * Flatten WhatsApp config for Laravel validator.
     */
    protected function flattenWhatsAppConfiguration(array $config): array
    {
        return [
            'driver' => $config['driver'] ?? null,

            'meta.base_url' => $config['meta']['base_url'] ?? null,
            'meta.api_version' => $config['meta']['api_version'] ?? null,
            'meta.access_token' => $config['meta']['access_token'] ?? null,
            'meta.business_account_id' => $config['meta']['business_account_id'] ?? null,
            'meta.phone_number_id' => $config['meta']['phone_number_id'] ?? null,
            'meta.webhook_verify_token' => $config['meta']['webhook_verify_token'] ?? null,

            'waha.base_url' => $config['waha']['base_url'] ?? null,
            'waha.api_key' => $config['waha']['api_key'] ?? null,
            'waha.session' => $config['waha']['session'] ?? null,
            'waha.timeout' => $config['waha']['timeout'] ?? null,

            'evolution.base_url' => $config['evolution']['base_url'] ?? null,
            'evolution.api_key' => $config['evolution']['api_key'] ?? null,
            'evolution.instance' => $config['evolution']['instance'] ?? null,
            'evolution.timeout' => $config['evolution']['timeout'] ?? null,
        ];
    }

    /**
     * Build WhatsApp validation rules based on selected driver.
     */
    protected function getWhatsAppValidationRules(?string $driver): array
    {
        $rules = [
            'driver' => ['required', 'in:meta,waha,evolution'],
        ];

        if ($driver === 'meta') {
            $rules = array_merge($rules, [
                'meta.base_url' => ['nullable', 'url', 'max:255'],
                'meta.api_version' => ['required', 'string', 'max:20'],
                'meta.access_token' => ['required', 'string', 'max:2048'],
                'meta.business_account_id' => ['required', 'string', 'max:255'],
                'meta.phone_number_id' => ['required', 'string', 'max:255'],
                'meta.webhook_verify_token' => ['nullable', 'string', 'max:255'],
            ]);
        } elseif ($driver === 'waha') {
            $rules = array_merge($rules, [
                'waha.base_url' => ['required', 'url', 'max:255'],
                'waha.api_key' => ['required', 'string', 'max:2048'],
                'waha.session' => ['required', 'string', 'max:255'],
                'waha.timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            ]);
        } elseif ($driver === 'evolution') {
            $rules = array_merge($rules, [
                'evolution.base_url' => ['required', 'url', 'max:255'],
                'evolution.api_key' => ['required', 'string', 'max:2048'],
                'evolution.instance' => ['required', 'string', 'max:255'],
                'evolution.timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            ]);
        }

        return $rules;
    }
}
