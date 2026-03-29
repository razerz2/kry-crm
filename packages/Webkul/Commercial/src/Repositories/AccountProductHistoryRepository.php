<?php

namespace Webkul\Commercial\Repositories;

use Webkul\Commercial\Contracts\AccountProductHistory;
use Webkul\Core\Eloquent\Repository;

class AccountProductHistoryRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return AccountProductHistory::class;
    }
}
