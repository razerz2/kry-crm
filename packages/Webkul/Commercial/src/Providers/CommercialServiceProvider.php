<?php

namespace Webkul\Commercial\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Commercial\Console\Commands\AudiencePreviewCommand;
use Webkul\Commercial\Repositories\CommercialCampaignDeliveryRepository;
use Webkul\Commercial\Services\Audience\CommercialAudienceService;
use Webkul\Commercial\Services\CommercialCampaignDeliveryService;
use Webkul\Commercial\Services\CommercialCampaignMetricsService;
use Webkul\Commercial\Services\CommercialCampaignService;
use Webkul\Commercial\Services\CommercialCampaignStateGuard;
use Webkul\Commercial\Services\Sending\EmailCampaignSender;
use Webkul\Commercial\Services\Sending\WhatsAppCampaignSender;
use Webkul\Commercial\Services\Template\CommercialCampaignTemplateRenderer;

class CommercialServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->mergeConfigFrom(__DIR__.'/../config/commercial.php', 'commercial');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AudiencePreviewCommand::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CommercialAudienceService::class, function () {
            return new CommercialAudienceService();
        });

        // StateGuard is stateless
        $this->app->singleton(CommercialCampaignStateGuard::class);

        $this->app->singleton(CommercialCampaignService::class, function ($app) {
            return new CommercialCampaignService(
                $app->make(CommercialAudienceService::class),
                $app->make(CommercialCampaignStateGuard::class),
            );
        });

        // Senders (stateless — can be shared)
        $this->app->singleton(EmailCampaignSender::class);
        $this->app->singleton(WhatsAppCampaignSender::class);

        // Template renderer (stateless)
        $this->app->singleton(CommercialCampaignTemplateRenderer::class);

        // Metrics service (stateless)
        $this->app->singleton(CommercialCampaignMetricsService::class);

        $this->app->singleton(CommercialCampaignDeliveryService::class, function ($app) {
            return new CommercialCampaignDeliveryService(
                $app->make(CommercialCampaignDeliveryRepository::class),
                $app->make(EmailCampaignSender::class),
                $app->make(WhatsAppCampaignSender::class),
                $app->make(CommercialCampaignTemplateRenderer::class),
                $app->make(CommercialCampaignMetricsService::class),
            );
        });
    }
}
