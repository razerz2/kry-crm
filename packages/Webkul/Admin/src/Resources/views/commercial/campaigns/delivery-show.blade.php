<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.delivery-show.title', ['id' => $delivery->id])
    </x-slot>

    @php
        $statusColors = [
            'sent'     => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed'   => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'skipped'  => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            'canceled' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
            'pending'  => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            'queued'   => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
            'sending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
        ];
        $levelColors = [
            'info'    => 'text-blue-600 dark:text-blue-400',
            'warning' => 'text-yellow-600 dark:text-yellow-400',
            'error'   => 'text-red-600 dark:text-red-400',
            'debug'   => 'text-gray-400 dark:text-gray-500',
        ];
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Header --}}
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1.5">
                <div class="text-lg font-bold dark:text-white">
                    @lang('admin::app.commercial.campaigns.delivery-show.heading', ['name' => $delivery->recipient_name ?? $delivery->id])
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$delivery->status] ?? 'bg-gray-100 text-gray-600' }}">
                        @lang('admin::app.commercial.campaigns.deliveries.statuses.' . $delivery->status)
                    </span>
                    <span class="text-gray-500">
                        @lang('admin::app.commercial.campaigns.channels.' . $delivery->channel)
                        @if ($delivery->provider)
                            · {{ $delivery->provider }}
                        @endif
                    </span>
                    <span class="text-gray-400">
                        @lang('admin::app.commercial.campaigns.delivery-show.campaign-ref', ['name' => $campaign->name])
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.deliveries', $campaign->id) }}" class="transparent-button">
                    @lang('admin::app.commercial.campaigns.delivery-show.back-btn')
                </a>
            </div>
        </div>

        <div class="flex gap-4 max-xl:flex-wrap">
            {{-- Left column: details --}}
            <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">

                {{-- Recipient details --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-sm font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.commercial.campaigns.delivery-show.recipient-section')
                    </p>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                @lang('admin::app.commercial.campaigns.audience.name')
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $delivery->recipient_name ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                @lang('admin::app.commercial.campaigns.audience.type')
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $delivery->entity_type ?? '—' }}</dd>
                        </div>

                        @if ($delivery->recipient_email)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    @lang('admin::app.commercial.campaigns.audience.email')
                                </dt>
                                <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $delivery->recipient_email }}</dd>
                            </div>
                        @endif

                        @if ($delivery->recipient_phone)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    @lang('admin::app.commercial.campaigns.audience.phone')
                                </dt>
                                <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $delivery->recipient_phone }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                @lang('admin::app.commercial.campaigns.deliveries.datagrid.channel')
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-200">
                                @lang('admin::app.commercial.campaigns.channels.' . $delivery->channel)
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                @lang('admin::app.commercial.campaigns.deliveries.datagrid.provider')
                            </dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $delivery->provider ?? '—' }}</dd>
                        </div>

                        @if ($delivery->sent_at)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    @lang('admin::app.commercial.campaigns.deliveries.datagrid.sent-at')
                                </dt>
                                <dd class="mt-0.5 text-gray-700 dark:text-gray-200">
                                    {{ $delivery->sent_at->format('d/m/Y H:i:s') }}
                                </dd>
                            </div>
                        @endif

                        @if ($delivery->failed_at)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    @lang('admin::app.commercial.campaigns.delivery-show.failed-at')
                                </dt>
                                <dd class="mt-0.5 text-red-600 dark:text-red-400">
                                    {{ $delivery->failed_at->format('d/m/Y H:i:s') }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Failure reason --}}
                @if ($delivery->failure_reason)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">
                            @lang('admin::app.commercial.campaigns.deliveries.datagrid.failure-reason')
                        </p>
                        <p class="text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ $delivery->failure_reason }}</p>
                    </div>
                @endif

                {{-- Subject --}}
                @if ($delivery->subject)
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            @lang('admin::app.commercial.campaigns.create.subject')
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-200">{{ $delivery->subject }}</p>
                    </div>
                @endif

                {{-- Rendered message --}}
                @if ($delivery->rendered_message)
                    <div
                        x-data="{ expanded: false }"
                        class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                @lang('admin::app.commercial.campaigns.create.message-body')
                            </p>
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                x-text="expanded ? '@lang('admin::app.commercial.campaigns.delivery-show.collapse')' : '@lang('admin::app.commercial.campaigns.delivery-show.expand')'"
                            ></button>
                        </div>

                        <div
                            class="overflow-hidden text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap rounded-md bg-gray-50 p-3 dark:bg-gray-800 transition-all"
                            :class="expanded ? 'max-h-none' : 'max-h-40'"
                        >{{ $delivery->rendered_message }}</div>
                    </div>
                @endif
            </div>

            {{-- Right column: logs timeline --}}
            <div class="flex w-[300px] max-w-full flex-col gap-2 max-sm:w-full">
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-sm font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.commercial.campaigns.delivery-show.logs-title')
                    </p>

                    @if ($delivery->logs->isNotEmpty())
                        <ol class="relative border-l border-gray-200 dark:border-gray-700">
                            @foreach ($delivery->logs->sortBy('created_at') as $log)
                                <li class="mb-4 ml-4">
                                    <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white dark:border-gray-900
                                        @if ($log->level === 'error') bg-red-500
                                        @elseif ($log->level === 'warning') bg-yellow-400
                                        @elseif ($log->level === 'info') bg-blue-500
                                        @else bg-gray-400 @endif
                                    "></div>

                                    <time class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}
                                    </time>

                                    <span class="ml-1.5 text-xs font-semibold uppercase {{ $levelColors[$log->level] ?? 'text-gray-400' }}">
                                        {{ $log->level }}
                                    </span>

                                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $log->message }}</p>

                                    @if (!empty($log->context_json))
                                        <details class="mt-0.5">
                                            <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                @lang('admin::app.commercial.campaigns.delivery-show.context')
                                            </summary>
                                            <pre class="mt-1 rounded bg-gray-100 p-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300 overflow-x-auto">{{ json_encode($log->context_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            @lang('admin::app.commercial.campaigns.delivery-show.no-logs')
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>
