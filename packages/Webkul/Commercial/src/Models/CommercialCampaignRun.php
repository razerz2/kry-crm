<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Commercial\Contracts\CommercialCampaignRun as CommercialCampaignRunContract;
use Webkul\User\Models\UserProxy;

class CommercialCampaignRun extends Model implements CommercialCampaignRunContract
{
    protected $table = 'commercial_campaign_runs';

    protected $fillable = [
        'commercial_campaign_id',
        'trigger_type',
        'status',
        'scheduled_for',
        'started_at',
        'finished_at',
        'audience_total',
        'audience_with_email',
        'audience_with_phone',
        'total_deliveries',
        'total_sent',
        'total_failed',
        'total_skipped',
        'total_canceled',
        'error_message',
        'meta_json',
        'created_by',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta_json' => 'array',
        'audience_total' => 'integer',
        'audience_with_email' => 'integer',
        'audience_with_phone' => 'integer',
        'total_deliveries' => 'integer',
        'total_sent' => 'integer',
        'total_failed' => 'integer',
        'total_skipped' => 'integer',
        'total_canceled' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommercialCampaignProxy::modelClass(), 'commercial_campaign_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CommercialCampaignDeliveryProxy::modelClass(), 'commercial_campaign_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }
}

