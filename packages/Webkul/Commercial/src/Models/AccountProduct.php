<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Webkul\Commercial\Contracts\AccountProduct as AccountProductContract;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\User\Models\UserProxy;

class AccountProduct extends Model implements AccountProductContract
{
    protected $table = 'account_products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'crm_product_id',
        'status',
        'started_at',
        'ended_at',
        'lost_reason',
        'notes',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status'     => AccountProductStatus::class,
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    /**
     * Get the parent entity (Person or Organization).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the CRM product this relationship belongs to.
     */
    public function crmProduct(): BelongsTo
    {
        return $this->belongsTo(CrmProductProxy::modelClass());
    }

    /**
     * Get the user responsible for this commercial relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the status change history for this relationship.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(AccountProductHistoryProxy::modelClass());
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, AccountProductStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter active customers only.
     */
    public function scopeCustomers($query)
    {
        return $query->where('status', AccountProductStatus::CUSTOMER->value);
    }
}
