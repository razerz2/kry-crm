<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.commercial.campaigns.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('commercial.campaigns.create'))
                    <a
                        href="{{ route('admin.commercial.campaigns.create') }}"
                        class="primary-button"
                    >
                        @lang('admin::app.commercial.campaigns.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.commercial.campaigns.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
