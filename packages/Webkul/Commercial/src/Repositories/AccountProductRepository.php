<?php

namespace Webkul\Commercial\Repositories;

use Webkul\Commercial\Contracts\AccountProduct;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\Core\Eloquent\Repository;

class AccountProductRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'entity_type',
        'entity_id',
        'crm_product_id',
        'status',
    ];

    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return AccountProduct::class;
    }

    /**
     * Find or create a commercial relationship for a given entity and product.
     */
    public function findOrCreateForEntity(
        string $entityType,
        int $entityId,
        int $crmProductId,
        AccountProductStatus $status = AccountProductStatus::LEAD
    ): \Webkul\Commercial\Contracts\AccountProduct {
        $record = $this->findOneWhere([
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'crm_product_id' => $crmProductId,
        ]);

        if ($record) {
            return $record;
        }

        return $this->create([
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'crm_product_id' => $crmProductId,
            'status'         => $status->value,
        ]);
    }

    /**
     * Transition the status of a commercial relationship.
     */
    public function transitionStatus(
        int $id,
        AccountProductStatus $newStatus,
        array $extra = []
    ): \Webkul\Commercial\Contracts\AccountProduct {
        $data = array_merge(['status' => $newStatus->value], $extra);

        if ($newStatus === AccountProductStatus::CUSTOMER && empty($extra['started_at'])) {
            $data['started_at'] = now();
        }

        if (
            in_array($newStatus, [AccountProductStatus::FORMER_CUSTOMER, AccountProductStatus::LOST])
            && empty($extra['ended_at'])
        ) {
            $data['ended_at'] = now();
        }

        return $this->update($data, $id);
    }
}
