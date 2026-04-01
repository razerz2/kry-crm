<?php

return [
    /**
     * General.
     */
    [
        'key' => 'general',
        'name' => 'admin::app.configuration.index.general.title',
        'info' => 'admin::app.configuration.index.general.info',
        'sort' => 1,
    ], [
        'key' => 'general.general',
        'name' => 'admin::app.configuration.index.general.general.title',
        'info' => 'admin::app.configuration.index.general.general.info',
        'icon' => 'icon-setting',
        'sort' => 1,
    ], [
        'key' => 'general.general.locale_settings',
        'name' => 'admin::app.configuration.index.general.general.locale-settings.title',
        'info' => 'admin::app.configuration.index.general.general.locale-settings.title-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'locale',
                'title' => 'admin::app.configuration.index.general.general.locale-settings.title',
                'type' => 'select',
                'default' => 'en',
                'options' => 'Webkul\Core\Core@locales',
            ],
        ],
    ], [
        'key' => 'general.general.admin_logo',
        'name' => 'admin::app.configuration.index.general.general.admin-logo.title',
        'info' => 'admin::app.configuration.index.general.general.admin-logo.title-info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'logo_image',
                'title' => 'admin::app.configuration.index.general.general.admin-logo.logo-image',
                'type' => 'image',
                'validation' => 'mimes:bmp,jpeg,jpg,png,webp,svg',
            ],
        ],
    ], [
        'key' => 'general.settings',
        'name' => 'admin::app.configuration.index.general.settings.title',
        'info' => 'admin::app.configuration.index.general.settings.info',
        'icon' => 'icon-configuration',
        'sort' => 2,
    ], [
        'key' => 'general.settings.footer',
        'name' => 'admin::app.configuration.index.general.settings.footer.title',
        'info' => 'admin::app.configuration.index.general.settings.footer.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'label',
                'title' => 'admin::app.configuration.index.general.settings.footer.powered-by',
                'type' => 'editor',
                'default' => 'Powered by <span style="color: rgb(14, 144, 217);"><a href="http://www.krayincrm.com" target="_blank">Krayin</a></span>, an open-source project by <span style="color: rgb(14, 144, 217);"><a href="https://webkul.com" target="_blank">Webkul</a></span>.',
                'tinymce' => true,
            ],
        ],
    ], [
        'key' => 'general.settings.menu',
        'name' => 'admin::app.configuration.index.general.settings.menu.title',
        'info' => 'admin::app.configuration.index.general.settings.menu.info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'dashboard',
                'title' => 'admin::app.configuration.index.general.settings.menu.dashboard',
                'type' => 'text',
                'default' => 'Dashboard',
                'validation' => 'max:20',
            ], [
                'name' => 'leads',
                'title' => 'admin::app.configuration.index.general.settings.menu.leads',
                'type' => 'text',
                'default' => 'Leads',
                'validation' => 'max:20',
            ], [
                'name' => 'quotes',
                'title' => 'admin::app.configuration.index.general.settings.menu.quotes',
                'type' => 'text',
                'default' => 'Quotes',
                'validation' => 'max:20',
            ], [
                'name' => 'activities',
                'title' => 'admin::app.configuration.index.general.settings.menu.activities',
                'type' => 'text',
                'default' => 'Activities',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts.contacts',
                'title' => 'admin::app.configuration.index.general.settings.menu.contacts',
                'type' => 'text',
                'default' => 'Contacts',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts.persons',
                'title' => 'admin::app.configuration.index.general.settings.menu.persons',
                'type' => 'text',
                'default' => 'Persons',
                'validation' => 'max:20',
            ], [
                'name' => 'contacts.organizations',
                'title' => 'admin::app.configuration.index.general.settings.menu.organizations',
                'type' => 'text',
                'default' => 'Organizations',
                'validation' => 'max:20',
            ], [
                'name' => 'products',
                'title' => 'admin::app.configuration.index.general.settings.menu.products',
                'type' => 'text',
                'default' => 'Products',
                'validation' => 'max:20',
            ], [
                'name' => 'settings',
                'title' => 'admin::app.configuration.index.general.settings.menu.settings',
                'type' => 'text',
                'default' => 'Settings',
                'validation' => 'max:20',
            ], [
                'name' => 'configuration',
                'title' => 'admin::app.configuration.index.general.settings.menu.configuration',
                'type' => 'text',
                'default' => 'Configuration',
                'validation' => 'max:20',
            ],
        ],
    ], [
        'key' => 'general.settings.menu_color',
        'name' => 'admin::app.configuration.index.general.settings.menu-color.title',
        'info' => 'admin::app.configuration.index.general.settings.menu-color.info',
        'sort' => 3,
        'fields' => [
            [
                'name' => 'brand_color',
                'title' => 'admin::app.configuration.index.general.settings.menu-color.brand-color',
                'type' => 'color',
                'default' => '#0E90D9',
            ],
        ],
    ], [
        'key' => 'email',
        'name' => 'admin::app.configuration.index.email.title',
        'info' => 'admin::app.configuration.index.email.info',
        'sort' => 3,
    ], [
        'key' => 'email.smtp',
        'name' => 'admin::app.configuration.index.email.smtp.title',
        'info' => 'admin::app.configuration.index.email.smtp.info',
        'icon' => 'icon-settings-mail',
        'sort' => 1,
    ], [
        'key' => 'email.smtp.account',
        'name' => 'admin::app.configuration.index.email.smtp.account.title',
        'info' => 'admin::app.configuration.index.email.smtp.account.title-info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'host',
                'title' => 'admin::app.configuration.index.email.smtp.account.host',
                'type' => 'text',
                'default' => env('MAIL_HOST', ''),
                'validation' => 'required|string|max:255',
            ], [
                'name' => 'port',
                'title' => 'admin::app.configuration.index.email.smtp.account.port',
                'type' => 'number',
                'default' => (int) env('MAIL_PORT', 587),
                'validation' => 'required|integer|min:1|max:65535',
            ], [
                'name' => 'encryption',
                'title' => 'admin::app.configuration.index.email.smtp.account.encryption',
                'type' => 'select',
                'default' => env('MAIL_ENCRYPTION', 'tls') ?: 'null',
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.email.smtp.account.encryption-options.tls',
                        'value' => 'tls',
                    ], [
                        'title' => 'admin::app.configuration.index.email.smtp.account.encryption-options.ssl',
                        'value' => 'ssl',
                    ], [
                        'title' => 'admin::app.configuration.index.email.smtp.account.encryption-options.null',
                        'value' => 'null',
                    ],
                ],
                'validation' => 'required|in:tls,ssl,null',
            ], [
                'name' => 'username',
                'title' => 'admin::app.configuration.index.email.smtp.account.username',
                'type' => 'text',
                'default' => env('MAIL_USERNAME', ''),
                'validation' => 'nullable|string|max:255',
            ], [
                'name' => 'password',
                'title' => 'admin::app.configuration.index.email.smtp.account.password',
                'type' => 'password',
                'default' => env('MAIL_PASSWORD', ''),
                'validation' => 'nullable|string|max:255',
            ], [
                'name' => 'from_name',
                'title' => 'admin::app.configuration.index.email.smtp.account.from-name',
                'type' => 'text',
                'default' => env('MAIL_FROM_NAME', env('APP_NAME', 'Krayin CRM')),
                'validation' => 'required|string|max:255',
            ], [
                'name' => 'from_address',
                'title' => 'admin::app.configuration.index.email.smtp.account.from-address',
                'type' => 'text',
                'default' => env('MAIL_FROM_ADDRESS', ''),
                'validation' => 'required|email|max:255',
            ], [
                'name' => 'timeout',
                'title' => 'admin::app.configuration.index.email.smtp.account.timeout',
                'type' => 'number',
                'default' => null,
                'validation' => 'nullable|integer|min:1|max:300',
            ],
        ],
    ], [
        'key' => 'whatsapp',
        'name' => 'admin::app.configuration.index.whatsapp.title',
        'info' => 'admin::app.configuration.index.whatsapp.info',
        'icon' => 'icon-settings-webhooks',
        'sort' => 4,
        'fields' => [
            [
                'name' => 'provider.driver',
                'title' => 'admin::app.configuration.index.whatsapp.provider.driver',
                'type' => 'select',
                'default' => env('COMMERCIAL_WHATSAPP_PROVIDER', 'waha') === 'meta_official' ? 'meta' : env('COMMERCIAL_WHATSAPP_PROVIDER', 'waha'),
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.whatsapp.provider.options.meta',
                        'value' => 'meta',
                    ], [
                        'title' => 'admin::app.configuration.index.whatsapp.provider.options.waha',
                        'value' => 'waha',
                    ], [
                        'title' => 'admin::app.configuration.index.whatsapp.provider.options.evolution',
                        'value' => 'evolution',
                    ],
                ],
                'validation' => 'required|in:meta,waha,evolution',
            ],

            [
                'name' => 'meta.base_url',
                'title' => 'admin::app.configuration.index.whatsapp.meta.base-url',
                'type' => 'text',
                'default' => 'https://graph.facebook.com',
                'depends' => 'provider.driver:meta',
                'validation' => 'nullable|url|max:255',
            ], [
                'name' => 'meta.api_version',
                'title' => 'admin::app.configuration.index.whatsapp.meta.api-version',
                'type' => 'text',
                'default' => 'v21.0',
                'depends' => 'provider.driver:meta',
                'validation' => 'required_if:whatsapp.provider.driver,meta|string|max:20',
            ], [
                'name' => 'meta.access_token',
                'title' => 'admin::app.configuration.index.whatsapp.meta.access-token',
                'type' => 'password',
                'depends' => 'provider.driver:meta',
                'validation' => 'required_if:whatsapp.provider.driver,meta|string|max:2048',
            ], [
                'name' => 'meta.business_account_id',
                'title' => 'admin::app.configuration.index.whatsapp.meta.business-account-id',
                'type' => 'text',
                'depends' => 'provider.driver:meta',
                'validation' => 'required_if:whatsapp.provider.driver,meta|string|max:255',
            ], [
                'name' => 'meta.phone_number_id',
                'title' => 'admin::app.configuration.index.whatsapp.meta.phone-number-id',
                'type' => 'text',
                'depends' => 'provider.driver:meta',
                'validation' => 'required_if:whatsapp.provider.driver,meta|string|max:255',
            ], [
                'name' => 'meta.webhook_verify_token',
                'title' => 'admin::app.configuration.index.whatsapp.meta.webhook-verify-token',
                'type' => 'password',
                'depends' => 'provider.driver:meta',
                'validation' => 'nullable|string|max:255',
            ],

            [
                'name' => 'waha.base_url',
                'title' => 'admin::app.configuration.index.whatsapp.waha.base-url',
                'type' => 'text',
                'depends' => 'provider.driver:waha',
                'validation' => 'required_if:whatsapp.provider.driver,waha|url|max:255',
            ], [
                'name' => 'waha.api_key',
                'title' => 'admin::app.configuration.index.whatsapp.waha.api-key',
                'type' => 'password',
                'depends' => 'provider.driver:waha',
                'validation' => 'required_if:whatsapp.provider.driver,waha|string|max:2048',
            ], [
                'name' => 'waha.session',
                'title' => 'admin::app.configuration.index.whatsapp.waha.session',
                'type' => 'text',
                'depends' => 'provider.driver:waha',
                'validation' => 'required_if:whatsapp.provider.driver,waha|string|max:255',
            ], [
                'name' => 'waha.timeout',
                'title' => 'admin::app.configuration.index.whatsapp.waha.timeout',
                'type' => 'number',
                'default' => 30,
                'depends' => 'provider.driver:waha',
                'validation' => 'nullable|integer|min:1|max:300',
            ],

            [
                'name' => 'evolution.base_url',
                'title' => 'admin::app.configuration.index.whatsapp.evolution.base-url',
                'type' => 'text',
                'depends' => 'provider.driver:evolution',
                'validation' => 'required_if:whatsapp.provider.driver,evolution|url|max:255',
            ], [
                'name' => 'evolution.api_key',
                'title' => 'admin::app.configuration.index.whatsapp.evolution.api-key',
                'type' => 'password',
                'depends' => 'provider.driver:evolution',
                'validation' => 'required_if:whatsapp.provider.driver,evolution|string|max:2048',
            ], [
                'name' => 'evolution.instance',
                'title' => 'admin::app.configuration.index.whatsapp.evolution.instance',
                'type' => 'text',
                'depends' => 'provider.driver:evolution',
                'validation' => 'required_if:whatsapp.provider.driver,evolution|string|max:255',
            ], [
                'name' => 'evolution.timeout',
                'title' => 'admin::app.configuration.index.whatsapp.evolution.timeout',
                'type' => 'number',
                'default' => 30,
                'depends' => 'provider.driver:evolution',
                'validation' => 'nullable|integer|min:1|max:300',
            ],
        ],
    ], [
        'key' => 'general.magic_ai',
        'name' => 'admin::app.configuration.index.magic-ai.title',
        'info' => 'admin::app.configuration.index.magic-ai.info',
        'icon' => 'icon-setting',
        'sort' => 5,
    ], [
        'key' => 'general.magic_ai.settings',
        'name' => 'admin::app.configuration.index.magic-ai.settings.title',
        'info' => 'admin::app.configuration.index.magic-ai.settings.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'enable',
                'title' => 'admin::app.configuration.index.magic-ai.settings.enable',
                'type' => 'boolean',
                'channel_based' => true,
            ], [
                'name' => 'api_key',
                'title' => 'admin::app.configuration.index.magic-ai.settings.api-key',
                'type' => 'password',
                'depends' => 'enable:1',
                'validation' => 'required_if:enable,1',
                'info' => 'admin::app.configuration.index.magic-ai.settings.api-key-info',
            ], [
                'name' => 'model',
                'title' => 'admin::app.configuration.index.magic-ai.settings.models.title',
                'type' => 'select',
                'channel_based' => true,
                'depends' => 'enable:1',
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.gpt-4o',
                        'value' => 'openai/chatgpt-4o-latest',
                    ], [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.gpt-4o-mini',
                        'value' => 'openai/gpt-4o-mini',
                    ], [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.gemini-2-0-flash-001',
                        'value' => 'google/gemini-2.0-flash-001',
                    ], [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.deepseek-r1',
                        'value' => 'deepseek/deepseek-r1-distill-llama-8b',
                    ], [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.llama-3-2-3b-instruct',
                        'value' => 'meta-llama/llama-3.2-3b-instruct',
                    ], [
                        'title' => 'admin::app.configuration.index.magic-ai.settings.models.grok-2-1212',
                        'value' => 'x-ai/grok-2-1212',
                    ],
                ],
            ], [
                'name' => 'other_model',
                'title' => 'admin::app.configuration.index.magic-ai.settings.other',
                'type' => 'text',
                'info' => 'admin::app.configuration.index.magic-ai.settings.other-model',
                'default' => null,
                'depends' => 'enable:1',
            ],
        ],
    ], [
        'key' => 'general.magic_ai.doc_generation',
        'name' => 'admin::app.configuration.index.magic-ai.settings.doc-generation',
        'info' => 'admin::app.configuration.index.magic-ai.settings.doc-generation-info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'enabled',
                'title' => 'admin::app.configuration.index.magic-ai.settings.enable',
                'type' => 'boolean',
            ],
        ],
    ],

];
