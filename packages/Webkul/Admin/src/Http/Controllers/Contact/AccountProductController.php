<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Commercial\Enums\AccountProductStatus;
use Webkul\Commercial\Repositories\AccountProductRepository;
use Webkul\Commercial\Repositories\CrmProductRepository;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;

class AccountProductController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AccountProductRepository $accountProductRepository,
        protected CrmProductRepository $crmProductRepository
    ) {}

    /**
     * Store or update a commercial relationship.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|in:persons,organizations',
            'entity_id' => 'required|integer',
            'crm_product_id' => 'required|integer|exists:crm_products,id',
            'status' => 'required|string|in:'.implode(',', AccountProductStatus::values()),
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
            'lost_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $morphMap = [
            'persons' => PersonProxy::modelClass(),
            'organizations' => OrganizationProxy::modelClass(),
        ];

        $entityType = $morphMap[$request->entity_type];

        $existing = $this->accountProductRepository->findOneWhere([
            'entity_type' => $entityType,
            'entity_id' => $request->entity_id,
            'crm_product_id' => $request->crm_product_id,
        ]);

        $data = [
            'entity_type' => $entityType,
            'entity_id' => $request->entity_id,
            'crm_product_id' => $request->crm_product_id,
            'status' => $request->status,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
            'lost_reason' => $request->lost_reason,
            'notes' => $request->notes,
            'user_id' => $request->user_id ?: null,
        ];

        if (
            $request->status === AccountProductStatus::CUSTOMER->value
            && empty($data['started_at'])
        ) {
            $data['started_at'] = now();
        }

        if (
            in_array($request->status, [AccountProductStatus::FORMER_CUSTOMER->value, AccountProductStatus::LOST->value])
            && empty($data['ended_at'])
        ) {
            $data['ended_at'] = now();
        }

        if ($existing) {
            $accountProduct = $this->accountProductRepository->update($data, $existing->id);
        } else {
            $accountProduct = $this->accountProductRepository->create($data);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.account-products.save-success'),
            'data' => $accountProduct->load('crmProduct', 'user'),
        ]);
    }

    /**
     * Remove a commercial relationship.
     */
    public function destroy(int $id): JsonResponse
    {
        $accountProduct = $this->accountProductRepository->findOrFail($id);

        $this->accountProductRepository->delete($id);

        return response()->json([
            'message' => trans('admin::app.contacts.account-products.delete-success'),
        ]);
    }
}
