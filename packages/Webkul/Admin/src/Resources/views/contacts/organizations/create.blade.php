
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.organizations.create.title')
    </x-slot>

    {!! view_render_event('admin.organizations.create.form.before') !!}

    <x-admin::form
        :action="route('admin.contacts.organizations.store')"
        method="POST"
    >
    
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.organizations.create.breadcrumbs.before') !!}

                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs name="contacts.organizations.create" />

                    {!! view_render_event('admin.organizations.create.breadcrumbs.before') !!}

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.contacts.organizations.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.organizations.create.save_buttons.before') !!}

                        <!-- Create button for person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.contacts.organizations.create.save-btn')
                        </button>

                        {!! view_render_event('admin.organizations.create.save_buttons.before') !!}
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.contacts.organizations.create.form_controls.before') !!}

                <x-admin::attributes
                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        'entity_type' => 'organizations',
                    ])"
                    :custom-validations="[
                        'name' => [
                            'max:100',
                        ],
                        'address' => [
                            'max:100',
                        ],
                        'postcode' => [
                            'postcode',
                        ],
                    ]"
                />

                {{-- Campos fiscais/comerciais --}}
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        CNPJ
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="cnpj"
                        :value="old('cnpj')"
                        placeholder="00.000.000/0000-00"
                        maxlength="18"
                    />

                    <x-admin::form.control-group.error control-name="cnpj" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Razão Social
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="legal_name"
                        :value="old('legal_name')"
                        placeholder="Razão social conforme CNPJ"
                    />

                    <x-admin::form.control-group.error control-name="legal_name" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        Nome Fantasia
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="trade_name"
                        :value="old('trade_name')"
                        placeholder="Nome fantasia"
                    />

                    <x-admin::form.control-group.error control-name="trade_name" />
                </x-admin::form.control-group>

                {!! view_render_event('admin.contacts.organizations.edit.form_controls.after') !!}
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.organizations.create.form.after') !!}
</x-admin::layouts>
