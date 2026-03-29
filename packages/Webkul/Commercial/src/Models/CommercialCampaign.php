<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Commercial\Contracts\CommercialCampaign as CommercialCampaignContract;
use Webkul\Commercial\Models\CommercialCampaignDeliveryProxy;
use Webkul\User\Models\UserProxy;

class CommercialCampaign extends Model implements CommercialCampaignContract
{
    protected $table = 'commercial_campaigns';

    /**
     * Statuses that block edits, re-freeze and recalculation.
     */
    public const LOCKED_STATUSES = ['sending', 'sent', 'partially_sent', 'failed'];

    protected $fillable = [
        'name',
        'description',
        'channel',
        'status',
        'subject',
        'message_body',
        'filters_json',
        'audience_generated_at',
        'dispatched_at',
        'sent_at',
        'total_audience',
        'total_with_email',
        'total_with_phone',
        'total_deliveries',
        'total_pending',
        'total_queued',
        'total_sending',
        'total_sent',
        'total_failed',
        'total_skipped',
        'total_canceled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'filters_json'          => 'array',
        'audience_generated_at' => 'datetime',
        'dispatched_at'         => 'datetime',
        'sent_at'               => 'datetime',
        'total_audience'        => 'integer',
        'total_with_email'      => 'integer',
        'total_with_phone'      => 'integer',
        'total_deliveries'      => 'integer',
        'total_pending'         => 'integer',
        'total_queued'          => 'integer',
        'total_sending'         => 'integer',
        'total_sent'            => 'integer',
        'total_failed'          => 'integer',
        'total_skipped'         => 'integer',
        'total_canceled'        => 'integer',
    ];

    /**
     * Get the audience members for this campaign.
     */
    public function audienceMembers(): HasMany
    {
        return $this->hasMany(CommercialCampaignAudienceProxy::modelClass(), 'commercial_campaign_id');
    }

    /**
     * Get the user who created the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    /**
     * Get the user who last updated the campaign.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'updated_by');
    }

    public function isDraft(): bool        { return $this->status === 'draft'; }
    public function isReady(): bool        { return $this->status === 'ready'; }
    public function isSending(): bool      { return $this->status === 'sending'; }
    public function isSent(): bool         { return $this->status === 'sent'; }
    public function isPartiallySent(): bool{ return $this->status === 'partially_sent'; }
    public function isFailed(): bool       { return $this->status === 'failed'; }
    public function isArchived(): bool     { return $this->status === 'archived'; }

    /**
     * True if the campaign has a frozen audience (at least one member).
     */
    public function hasAudience(): bool
    {
        return $this->audience_generated_at !== null && $this->total_audience > 0;
    }

    /**
     * True if the campaign is in a state that blocks edits and re-freeze.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
    }

    /**
     * True if the campaign can be dispatched right now.
     */
    public function canDispatch(): bool
    {
        return $this->status === 'ready' && $this->hasAudience();
    }

    /**
     * Deliveries relationship.
     */
    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CommercialCampaignDeliveryProxy::modelClass(), 'commercial_campaign_id');
    }
}
