<?php

namespace Webkul\Core\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Webkul\Core\Acl;
use Webkul\Core\Console\Commands\Version;
use Webkul\Core\Core;
use Webkul\Core\Facades\Acl as AclFacade;
use Webkul\Core\Facades\Core as CoreFacade;
use Webkul\Core\Facades\Menu as MenuFacade;
use Webkul\Core\Facades\SystemConfig as SystemConfigFacade;
use Webkul\Core\Menu;
use Webkul\Core\SystemConfig;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function boot()
    {
        include __DIR__.'/../Http/helpers.php';

        $this->applyConfiguredSmtpSettings();
        $this->applyConfiguredWhatsAppSettings();

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'core');

        $this->publishes([
            dirname(__DIR__).'/Config/concord.php' => config_path('concord.php'),
            dirname(__DIR__).'/Config/cors.php' => config_path('cors.php'),
            dirname(__DIR__).'/Config/sanctum.php' => config_path('sanctum.php'),
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();

        $this->registerFacades();
    }

    /**
     * Register Bouncer as a singleton.
     *
     * @return void
     */
    protected function registerFacades()
    {
        $loader = AliasLoader::getInstance();

        $loader->alias('acl', AclFacade::class);

        $loader->alias('core', CoreFacade::class);

        $loader->alias('system_config', SystemConfigFacade::class);

        $loader->alias('menu', MenuFacade::class);

        $this->app->singleton('acl', fn () => app(Acl::class));

        $this->app->singleton('core', fn () => app(Core::class));

        $this->app->singleton('system_config', fn () => app()->make(SystemConfig::class));

        $this->app->singleton('menu', fn () => app()->make(Menu::class));
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Version::class,
            ]);
        }
    }

    /**
     * Apply SMTP configuration stored in core settings to Laravel mail config.
     */
    protected function applyConfiguredSmtpSettings(): void
    {
        try {
            if (! Schema::hasTable('core_config')) {
                return;
            }

            $host = core()->getConfigData('email.smtp.host');
            $port = core()->getConfigData('email.smtp.port');
            $encryption = core()->getConfigData('email.smtp.encryption');
            $username = core()->getConfigData('email.smtp.username');
            $password = core()->getConfigData('email.smtp.password');
            $fromName = core()->getConfigData('email.smtp.from_name');
            $fromAddress = core()->getConfigData('email.smtp.from_address');
            $timeout = core()->getConfigData('email.smtp.timeout');

            if (
                empty($host)
                || empty($fromAddress)
            ) {
                return;
            }

            if ($encryption === 'null') {
                $encryption = null;
            }

            if ($timeout === '') {
                $timeout = null;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) $port,
                'mail.mailers.smtp.encryption' => $encryption,
                'mail.mailers.smtp.username' => $username ?: null,
                'mail.mailers.smtp.password' => $password ?: null,
                'mail.mailers.smtp.timeout' => $timeout !== null ? (int) $timeout : null,
                'mail.from.name' => $fromName ?: config('mail.from.name'),
                'mail.from.address' => $fromAddress ?: config('mail.from.address'),
            ]);

            if ($this->app->bound('mail.manager')) {
                $this->app->make('mail.manager')->purge('smtp');
            }
        } catch (Throwable) {
            // If DB/config is not available yet (e.g. during installation), keep default mail config.
        }
    }

    /**
     * Apply WhatsApp provider selected in core settings for future campaign usage.
     */
    protected function applyConfiguredWhatsAppSettings(): void
    {
        try {
            if (! Schema::hasTable('core_config')) {
                return;
            }

            $driver = core()->getConfigData('whatsapp.provider.driver');

            if (empty($driver)) {
                return;
            }

            $provider = $driver === 'meta' ? 'meta_official' : $driver;

            config([
                'commercial.campaign.whatsapp_provider' => $provider,
            ]);
        } catch (Throwable) {
            // Keep existing commercial provider config if dynamic settings are unavailable.
        }
    }
}
