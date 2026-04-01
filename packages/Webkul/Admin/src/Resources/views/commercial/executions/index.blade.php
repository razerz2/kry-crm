<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.executions.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.commercial.executions.index.title')
                </div>

                @if ($campaign)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('admin::app.commercial.executions.index.scoped-campaign', ['name' => $campaign->name])
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.index') }}" class="transparent-button">
                    @lang('admin::app.commercial.executions.index.back-to-campaigns')
                </a>

                @if ($campaign)
                    <a href="{{ route('admin.commercial.campaigns.show', $campaign->id) }}" class="secondary-button">
                        @lang('admin::app.commercial.executions.index.view-campaign')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.commercial.executions.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
