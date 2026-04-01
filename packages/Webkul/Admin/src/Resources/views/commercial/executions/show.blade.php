<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.executions.show.title', ['id' => $run->id])
    </x-slot>

    @php
        $statusClass = match($run->status) {
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'running' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'canceled' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1.5">
                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.commercial.executions.show.heading', ['id' => $run->id])
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                        @lang('admin::app.commercial.campaigns.schedule.run-statuses.' . $run->status)
                    </span>

                    <span>
                        @lang('admin::app.commercial.executions.trigger-types.' . $run->trigger_type)
                    </span>

                    @if ($run->campaign)
                        <span>
                            <a
                                href="{{ route('admin.commercial.campaigns.show', $run->campaign->id) }}"
                                class="text-brandColor hover:underline"
                            >
                                {{ $run->campaign->name }}
                            </a>
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.executions.index') }}" class="transparent-button">
                    @lang('admin::app.commercial.executions.show.back-btn')
                </a>

                @if ($run->campaign)
                    <a href="{{ route('admin.commercial.campaigns.show', $run->campaign->id) }}" class="transparent-button">
                        @lang('admin::app.commercial.executions.show.view-campaign')
                    </a>
                @endif

                <a
                    href="{{ route('admin.commercial.deliveries.index', ['campaign_id' => $run->commercial_campaign_id, 'run_id' => $run->id]) }}"
                    class="primary-button"
                >
                    @lang('admin::app.commercial.executions.show.open-delivery-audit')
                </a>
            </div>
        </div>

        <div class="grid grid-cols-5 gap-3 max-xl:grid-cols-2 max-sm:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.total-deliveries')</div>
                <div class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">{{ number_format((int) $run->total_deliveries) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.sent')</div>
                <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format((int) $run->total_sent) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.failed')</div>
                <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format((int) $run->total_failed) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.skipped')</div>
                <div class="mt-1 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ number_format((int) $run->total_skipped) }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.canceled')</div>
                <div class="mt-1 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ number_format((int) $run->total_canceled) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.commercial.executions.show.run-info')
                </p>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.scheduled-for')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->scheduled_for?->format('d/m/Y H:i:s') ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.started-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->started_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.finished-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->finished_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.execution-type')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">@lang('admin::app.commercial.executions.trigger-types.' . $run->trigger_type)</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.show.created-by')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->creator?->name ?: '-' }}</dd>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.commercial.executions.show.campaign-info')
                </p>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.campaign')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">
                        @if ($run->campaign)
                            <a href="{{ route('admin.commercial.campaigns.show', $run->campaign->id) }}" class="text-brandColor hover:underline">
                                {{ $run->campaign->name }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.executions.datagrid.next-run-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->campaign?->next_run_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.schedule.last-run-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $run->campaign?->last_run_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    @lang('admin::app.commercial.executions.show.delivery-list')
                </p>
            </div>

            <x-admin::datagrid :src="route('admin.commercial.deliveries.index', ['campaign_id' => $run->commercial_campaign_id, 'run_id' => $run->id])">
                <x-admin::shimmer.datagrid />
            </x-admin::datagrid>
        </div>
    </div>
</x-admin::layouts>
