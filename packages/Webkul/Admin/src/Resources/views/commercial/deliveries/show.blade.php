<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.delivery-audit.show.title', ['id' => $delivery->id])
    </x-slot>

    @php
        $statusClass = match($delivery->status) {
            'sent' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'pending', 'queued', 'sending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'deduplicated', 'ignored', 'skipped', 'canceled' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };

        $entityType = str_contains(strtolower((string) $delivery->entity_type), 'organization')
            ? trans('admin::app.commercial.delivery-audit.entity-types.organization')
            : trans('admin::app.commercial.delivery-audit.entity-types.person');

        $destination = $delivery->recipient_email ?: ($delivery->recipient_phone ?: ($delivery->normalized_destination ?: '-'));
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1.5">
                <div class="text-lg font-bold dark:text-white">
                    @lang('admin::app.commercial.delivery-audit.show.heading', ['id' => $delivery->id])
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                        @lang('admin::app.commercial.delivery-audit.statuses.' . $delivery->status)
                    </span>
                    <span>{{ $entityType }}</span>
                    <span>@lang('admin::app.commercial.campaigns.channels.' . $delivery->channel)</span>
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a
                    href="{{ route('admin.commercial.deliveries.index', ['campaign_id' => $delivery->commercial_campaign_id, 'run_id' => $delivery->commercial_campaign_run_id]) }}"
                    class="transparent-button"
                >
                    @lang('admin::app.commercial.delivery-audit.show.back-btn')
                </a>

                @if ($delivery->campaign)
                    <a href="{{ route('admin.commercial.campaigns.show', $delivery->campaign->id) }}" class="transparent-button">
                        @lang('admin::app.commercial.delivery-audit.show.view-campaign')
                    </a>
                @endif

                @if ($delivery->run)
                    <a href="{{ route('admin.commercial.executions.show', $delivery->run->id) }}" class="secondary-button">
                        @lang('admin::app.commercial.delivery-audit.show.view-run')
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-4 text-sm font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.commercial.delivery-audit.show.delivery-info')
                </p>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.campaign')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">
                        @if ($delivery->campaign)
                            <a href="{{ route('admin.commercial.campaigns.show', $delivery->campaign->id) }}" class="text-brandColor hover:underline">
                                {{ $delivery->campaign->name }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.run')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">
                        @if ($delivery->run)
                            <a href="{{ route('admin.commercial.executions.show', $delivery->run->id) }}" class="text-brandColor hover:underline">
                                #{{ $delivery->run->id }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.entity')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $delivery->recipient_name ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.destination')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $destination }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.normalized-destination')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $delivery->normalized_destination ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.provider-message-id')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $delivery->provider_message_id ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.sent-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.delivery-audit.datagrid.failed-at')</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ $delivery->failed_at?->format('d/m/Y H:i:s') ?: '-' }}</dd>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-4 text-sm font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.commercial.delivery-audit.show.logs-title')
                </p>

                @if ($delivery->logs->isNotEmpty())
                    <ol class="relative border-l border-gray-200 dark:border-gray-700">
                        @foreach ($delivery->logs->sortBy('created_at') as $log)
                            <li class="mb-4 ml-4">
                                <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-gray-400 dark:border-gray-900"></div>

                                <time class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}
                                </time>

                                <span class="ml-1.5 text-xs font-semibold uppercase text-gray-500 dark:text-gray-300">
                                    {{ $log->level }}
                                </span>

                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $log->message }}</p>

                                @if (!empty($log->context_json))
                                    <details class="mt-0.5">
                                        <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            @lang('admin::app.commercial.delivery-audit.show.context')
                                        </summary>
                                        <pre class="mt-1 overflow-x-auto rounded bg-gray-100 p-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ json_encode($log->context_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500">
                        @lang('admin::app.commercial.delivery-audit.show.no-logs')
                    </p>
                @endif
            </div>
        </div>

        @if ($delivery->failure_reason)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider">
                    @lang('admin::app.commercial.delivery-audit.datagrid.error')
                </p>

                <p class="whitespace-pre-wrap">{{ $delivery->failure_reason }}</p>
            </div>
        @endif
    </div>
</x-admin::layouts>
