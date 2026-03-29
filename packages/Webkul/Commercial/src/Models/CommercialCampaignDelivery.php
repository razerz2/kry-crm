<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Commercial\Contracts\CommercialCampaignDelivery as CommercialCampaignDeliveryContract;
use Webkul\User\Models\UserProxy;

class CommercialCampaignDelivery extends Model implements CommercialCampaignDeliveryContract
{
    protected $table = 'commercial_campaign_deliveries';

    protected $fillable = [
        'commercial_campaign_id',
        'commercial_campaign_audience_id',
        'entity_type',
        'entity_id',
        'channel',
        'provider',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'subject',
        'rendered_message',
        'status',
        'failure_reason',
        'provider_message_id',
        'queued_at',
        'sent_at',
        'failed_at',
        'created_by',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommercialCampaignProxy::modelClass(), 'commercial_campaign_id');
    }

    public function audienceMember(): BelongsTo
    {
        return $this->belongsTo(CommercialCampaignAudienceProxy::modelClass(), 'commercial_campaign_audience_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CommercialCampaignDeliveryLogProxy::modelClass(), 'commercial_campaign_delivery_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    /* ── Status helpers ─────────────────────────────────────────── */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['sent', 'failed', 'skipped', 'canceled']);
    }
}
