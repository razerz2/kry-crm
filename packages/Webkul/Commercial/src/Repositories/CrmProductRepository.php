<?php

namespace Webkul\Commercial\Repositories;

use Webkul\Commercial\Contracts\CrmProduct;
use Webkul\Core\Eloquent\Repository;

class CrmProductRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'slug',
    ];

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return CrmProduct::class;
    }
}
