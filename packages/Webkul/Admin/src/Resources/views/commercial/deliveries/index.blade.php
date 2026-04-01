<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.delivery-audit.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.commercial.delivery-audit.index.title')
                </div>

                @if ($campaign || $run)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($campaign)
                            @lang('admin::app.commercial.delivery-audit.index.scoped-campaign', ['name' => $campaign->name])
                        @endif

                        @if ($run)
                            @if ($campaign)
                                <span class="mx-1">|</span>
                            @endif

                            @lang('admin::app.commercial.delivery-audit.index.scoped-run', ['id' => $run->id])
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.index') }}" class="transparent-button">
                    @lang('admin::app.commercial.delivery-audit.index.back-to-campaigns')
                </a>

                @if ($campaign)
                    <a href="{{ route('admin.commercial.executions.index', ['campaign_id' => $campaign->id]) }}" class="transparent-button">
                        @lang('admin::app.commercial.delivery-audit.index.view-executions')
                    </a>
                @endif

                @if ($run)
                    <a href="{{ route('admin.commercial.executions.show', $run->id) }}" class="secondary-button">
                        @lang('admin::app.commercial.delivery-audit.index.view-run')
                    </a>
                @endif
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.commercial.deliveries.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
