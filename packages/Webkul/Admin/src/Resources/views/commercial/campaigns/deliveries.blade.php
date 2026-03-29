<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.deliveries.title', ['name' => $campaign->name])
    </x-slot>

    <div class="flex flex-col gap-4">
        {{-- Header --}}
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    {{ $campaign->name }}
                    <span class="ml-2 text-sm font-normal text-gray-500">—
                        @lang('admin::app.commercial.campaigns.deliveries.title-suffix')
                    </span>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded-full px-2 py-0.5 font-medium
                        @if($campaign->status === 'sent') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($campaign->status === 'sending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @elseif($campaign->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @elseif($campaign->status === 'partially_sent') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                        @else bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 @endif
                    ">
                        @lang('admin::app.commercial.campaigns.statuses.' . $campaign->status)
                    </span>

                    @if ($campaign->dispatched_at)
                        <span class="text-gray-500">
                            @lang('admin::app.commercial.campaigns.deliveries.dispatched-at', [
                                'date' => $campaign->dispatched_at->format('d/m/Y H:i')
                            ])
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.show', $campaign->id) }}" class="transparent-button">
                    @lang('admin::app.commercial.campaigns.deliveries.back-btn')
                </a>

                @if (bouncer()->hasPermission('commercial.campaigns.view'))
                    <form action="{{ route('admin.commercial.campaigns.recalculate_metrics', $campaign->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="secondary-button">
                            @lang('admin::app.commercial.campaigns.metrics.recalculate-btn')
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Stats --}}
        @include('admin::commercial.campaigns.partials.delivery-stats', ['stats' => $stats])

        {{-- DataGrid --}}
        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    @lang('admin::app.commercial.campaigns.deliveries.list-title')
                </p>
            </div>

            <x-admin::datagrid :src="route('admin.commercial.campaigns.deliveries', $campaign->id)">
                <x-admin::shimmer.datagrid />
            </x-admin::datagrid>
        </div>
    </div>
</x-admin::layouts>
