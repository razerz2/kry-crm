<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Commercial\Contracts\CommercialCampaignDeliveryLog as CommercialCampaignDeliveryLogContract;

class CommercialCampaignDeliveryLog extends Model implements CommercialCampaignDeliveryLogContract
{
    protected $table = 'commercial_campaign_delivery_logs';

    protected $fillable = [
        'commercial_campaign_delivery_id',
        'level',
        'message',
        'context_json',
    ];

    protected $casts = [
        'context_json' => 'array',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CommercialCampaignDeliveryProxy::modelClass(), 'commercial_campaign_delivery_id');
    }
}
