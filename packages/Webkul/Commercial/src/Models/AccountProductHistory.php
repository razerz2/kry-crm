<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Commercial\Contracts\AccountProductHistory as AccountProductHistoryContract;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\UserProxy;

class AccountProductHistory extends Model implements AccountProductHistoryContract
{
    protected $table = 'account_product_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_product_id',
        'lead_id',
        'old_status',
        'new_status',
        'changed_by',
        'source',
        'notes',
    ];

    /**
     * Get the account product this history belongs to.
     */
    public function accountProduct(): BelongsTo
    {
        return $this->belongsTo(AccountProductProxy::modelClass());
    }

    /**
     * Get the lead that triggered this change.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the user who made this change.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'changed_by');
    }
}
