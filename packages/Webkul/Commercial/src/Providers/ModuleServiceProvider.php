<?php

namespace Webkul\Commercial\Providers;

use Webkul\Commercial\Models\AccountProduct;
use Webkul\Commercial\Models\AccountProductHistory;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignAudience;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Models\CommercialCampaignDeliveryLog;
use Webkul\Commercial\Models\CommercialCampaignRun;
use Webkul\Commercial\Models\CrmProduct;
use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        CrmProduct::class,
        AccountProduct::class,
        AccountProductHistory::class,
        CommercialCampaign::class,
        CommercialCampaignAudience::class,
        CommercialCampaignRun::class,
        CommercialCampaignDelivery::class,
        CommercialCampaignDeliveryLog::class,
    ];
}
