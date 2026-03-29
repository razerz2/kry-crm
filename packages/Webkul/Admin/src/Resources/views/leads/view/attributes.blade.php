{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>@lang('admin::app.leads.view.attributes.title')</h4>
                
                @if (bouncer()->hasPermission('leads.edit'))
                    <a
                        href="{{ route('admin.leads.edit', $lead->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                        target="_blank"
                    ></a>
                @endif
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.leads.view.attributes.form_controls.before', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, () => {})">
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.before', ['lead' => $lead]) !!}
        
                    <x-admin::attributes.view
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            'entity_type' => 'leads',
                            ['code', 'NOTIN', ['title', 'description', 'lead_pipeline_id', 'lead_pipeline_stage_id']]
                        ])"
                        :entity="$lead"
                        :url="route('admin.leads.attributes.update', $lead->id)"
                        :allow-edit="true"
                    />
        
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.after', ['lead' => $lead]) !!}
                </form>
            </x-admin::form>
        
            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $lead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

@if ($lead->crmProduct)
    <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
        <h4 class="text-sm font-semibold dark:text-white">
            @lang('admin::app.leads.common.crm-product')
        </h4>

        <div class="flex items-center gap-2">
            <span class="inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                {{ $lead->crmProduct->name }}
            </span>

            @php
                $existingAccountProduct = \Webkul\Commercial\Models\AccountProduct::where('crm_product_id', $lead->crm_product_id)
                    ->where(function ($q) use ($lead) {
                        if ($lead->person_id) {
                            $personClass = \Webkul\Contact\Models\PersonProxy::modelClass();
                            $q->where('entity_type', $personClass)->where('entity_id', $lead->person_id);
                        }
                        if ($lead->person_id && $lead->person?->organization_id) {
                            $orgClass = \Webkul\Contact\Models\OrganizationProxy::modelClass();
                            $q->orWhere(function ($q2) use ($lead, $orgClass) {
                                $q2->where('entity_type', $orgClass)->where('entity_id', $lead->person->organization_id);
                            });
                        }
                    })
                    ->first();
            @endphp

            @if ($existingAccountProduct)
                @php
                    $statusColors = [
                        'lead'              => 'bg-blue-100 text-blue-700',
                        'prospect'          => 'bg-purple-100 text-purple-700',
                        'opportunity'       => 'bg-yellow-100 text-yellow-700',
                        'customer'          => 'bg-green-100 text-green-700',
                        'inactive_customer' => 'bg-gray-200 text-gray-600',
                        'former_customer'   => 'bg-orange-100 text-orange-600',
                        'lost'              => 'bg-red-100 text-red-600',
                    ];
                    $statusValue = $existingAccountProduct->status->value ?? $existingAccountProduct->status;
                    $statusLabel = $existingAccountProduct->status instanceof \Webkul\Commercial\Enums\AccountProductStatus
                        ? $existingAccountProduct->status->label()
                        : $statusValue;
                    $color = $statusColors[$statusValue] ?? 'bg-gray-100 text-gray-500';
                @endphp

                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">
                    {{ $statusLabel }}
                </span>
            @endif
        </div>
    </div>
@endif

{!! view_render_event('admin.leads.view.attributes.after', ['lead' => $lead]) !!}
