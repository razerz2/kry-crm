@php
    $f = $filters ?? [];
    $statuses = [
        'lead'              => 'Lead',
        'prospect'          => 'Prospect',
        'opportunity'       => 'Oportunidade',
        'customer'          => 'Cliente',
        'inactive_customer' => 'Cliente Inativo',
        'former_customer'   => 'Ex-Cliente',
        'lost'              => 'Perdido',
    ];
@endphp

<x-admin::accordion :is-active="true">
    <x-slot:header>
        <div class="flex items-center justify-between">
            <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                @lang('admin::app.commercial.campaigns.filters.title')
            </p>
        </div>
    </x-slot>

    <x-slot:content>
        {{-- Entity Type --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.entity-type')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="select"
                    name="filter_entity_type"
                    :value="$f['entity_type'] ?? 'both'"
                >
                    <option value="both" {{ ($f['entity_type'] ?? 'both') === 'both' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.entity-both')
                    </option>
                    <option value="person" {{ ($f['entity_type'] ?? '') === 'person' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.entity-person')
                    </option>
                    <option value="organization" {{ ($f['entity_type'] ?? '') === 'organization' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.entity-organization')
                    </option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>

        {{-- CRM Products --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.crm-products')
                </x-admin::form.control-group.label>

                <select
                    name="filter_crm_product_ids[]"
                    multiple
                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                >
                    @foreach ($products as $product)
                        <option
                            value="{{ $product->id }}"
                            {{ in_array($product->id, $f['crm_product_ids'] ?? []) ? 'selected' : '' }}
                        >
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @lang('admin::app.commercial.campaigns.filters.crm-products-hint')
                </p>
            </x-admin::form.control-group>
        </div>

        {{-- Commercial Statuses --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.commercial-statuses')
                </x-admin::form.control-group.label>

                <select
                    name="filter_commercial_statuses[]"
                    multiple
                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                >
                    @foreach ($statuses as $value => $label)
                        <option
                            value="{{ $value }}"
                            {{ in_array($value, $f['commercial_statuses'] ?? []) ? 'selected' : '' }}
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </x-admin::form.control-group>
        </div>

        {{-- Segment --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.segment')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="select"
                    name="filter_segment"
                    :value="$f['segment'] ?? ''"
                >
                    <option value="">@lang('admin::app.commercial.campaigns.filters.segment-none')</option>
                    <option value="customer_any" {{ ($f['segment'] ?? '') === 'customer_any' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.segment-customer-any')
                    </option>
                    <option value="non_customer" {{ ($f['segment'] ?? '') === 'non_customer' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.segment-non-customer')
                    </option>
                    <option value="has_link" {{ ($f['segment'] ?? '') === 'has_link' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.segment-has-link')
                    </option>
                    <option value="no_link" {{ ($f['segment'] ?? '') === 'no_link' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.filters.segment-no-link')
                    </option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>

        {{-- Channel Filter --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.channel-filter')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="select"
                    name="filter_channel"
                    :value="$f['channel'] ?? ''"
                >
                    <option value="">@lang('admin::app.commercial.campaigns.filters.channel-any')</option>
                    <option value="email" {{ ($f['channel'] ?? '') === 'email' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.channels.email')
                    </option>
                    <option value="whatsapp" {{ ($f['channel'] ?? '') === 'whatsapp' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.channels.whatsapp')
                    </option>
                    <option value="both" {{ ($f['channel'] ?? '') === 'both' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.channels.both')
                    </option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>

        {{-- Checkboxes --}}
        <div class="mb-4 flex flex-col gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="filter_only_with_email"
                    value="1"
                    {{ !empty($f['only_with_email']) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600"
                />
                @lang('admin::app.commercial.campaigns.filters.only-with-email')
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="filter_only_with_phone"
                    value="1"
                    {{ !empty($f['only_with_phone']) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600"
                />
                @lang('admin::app.commercial.campaigns.filters.only-with-phone')
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="filter_only_primary_contact"
                    value="1"
                    {{ !empty($f['only_primary_contact_if_organization']) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600"
                />
                @lang('admin::app.commercial.campaigns.filters.only-primary-contact')
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="filter_include_inactive_customer"
                    value="1"
                    {{ ($f['include_inactive_customer'] ?? true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600"
                />
                @lang('admin::app.commercial.campaigns.filters.include-inactive-customer')
            </label>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="filter_include_former_customer"
                    value="1"
                    {{ ($f['include_former_customer'] ?? true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600"
                />
                @lang('admin::app.commercial.campaigns.filters.include-former-customer')
            </label>
        </div>

        {{-- Search --}}
        <div class="mb-4">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.filters.search')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="text"
                    name="filter_search"
                    :value="$f['search'] ?? ''"
                    :placeholder="trans('admin::app.commercial.campaigns.filters.search-placeholder')"
                />
            </x-admin::form.control-group>
        </div>
    </x-slot>
</x-admin::accordion>
