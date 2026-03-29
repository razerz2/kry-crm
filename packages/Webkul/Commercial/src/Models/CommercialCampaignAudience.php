<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Commercial\Contracts\CommercialCampaignAudience as CommercialCampaignAudienceContract;

class CommercialCampaignAudience extends Model implements CommercialCampaignAudienceContract
{
    protected $table = 'commercial_campaign_audience';

    protected $fillable = [
        'commercial_campaign_id',
        'entity_type',
        'entity_id',
        'display_name',
        'organization_name',
        'primary_contact_person_id',
        'primary_contact_name',
        'email',
        'phone',
        'available_channels',
        'crm_products',
        'commercial_statuses',
        'source_summary',
        'payload_json',
    ];

    protected $casts = [
        'available_channels'  => 'array',
        'crm_products'        => 'array',
        'commercial_statuses' => 'array',
        'payload_json'        => 'array',
    ];

    /**
     * Get the campaign this audience member belongs to.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommercialCampaignProxy::modelClass(), 'commercial_campaign_id');
    }
}
