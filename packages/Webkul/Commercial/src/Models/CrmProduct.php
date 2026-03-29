<?php

namespace Webkul\Commercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Commercial\Contracts\CrmProduct as CrmProductContract;

class CrmProduct extends Model implements CrmProductContract
{
    protected $table = 'crm_products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all account-product relationships for this product.
     */
    public function accountProducts(): HasMany
    {
        return $this->hasMany(AccountProductProxy::modelClass());
    }

    /**
     * Get only active customer relationships for this product.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(AccountProductProxy::modelClass())
            ->where('status', 'customer');
    }
}
