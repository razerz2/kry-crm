<?php

namespace Webkul\Commercial\Repositories;

use Webkul\Commercial\Models\CommercialCampaignDeliveryLogProxy;
use Webkul\Core\Eloquent\Repository;

class CommercialCampaignDeliveryLogRepository extends Repository
{
    public function model(): string
    {
        return CommercialCampaignDeliveryLogProxy::modelClass();
    }
}
