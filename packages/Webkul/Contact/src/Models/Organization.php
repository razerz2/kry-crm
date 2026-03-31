<?php

namespace Webkul\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Commercial\Models\AccountProductProxy;
use Webkul\Contact\Contracts\Organization as OrganizationContract;
use Webkul\User\Models\UserProxy;

class Organization extends Model implements OrganizationContract
{
    use CustomAttribute;

    protected $casts = [
        'address' => 'array',
        'emails' => 'array',
        'contact_numbers' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'cnpj',
        'legal_name',
        'trade_name',
        'address',
        'emails',
        'contact_numbers',
        'user_id',
    ];

    /**
     * Get persons.
     *
     * @return HasMany
     */
    public function persons()
    {
        return $this->hasMany(PersonProxy::modelClass());
    }

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get all commercial relationships with CRM products.
     */
    public function accountProducts(): MorphMany
    {
        return $this->morphMany(AccountProductProxy::modelClass(), 'entity');
    }

    /**
     * Get only active customer relationships with CRM products.
     */
    public function activeProducts(): MorphMany
    {
        return $this->morphMany(AccountProductProxy::modelClass(), 'entity')
            ->where('status', 'customer');
    }
}
