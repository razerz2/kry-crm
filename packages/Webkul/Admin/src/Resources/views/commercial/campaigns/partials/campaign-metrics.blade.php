{{--
    Full metrics panel for a campaign.

    Required variables:
        $campaign  — CommercialCampaign model
        $metrics   — array with keys: status, channels, providers, errors
--}}
@php
    $statusColors = [
        'sent'     => ['bg' => 'bg-green-500',  'light' => 'bg-green-50 dark:bg-green-950',  'text' => 'text-green-700 dark:text-green-300'],
        'failed'   => ['bg' => 'bg-red-500',    'light' => 'bg-red-50 dark:bg-red-950',      'text' => 'text-red-700 dark:text-red-300'],
        'skipped'  => ['bg' => 'bg-orange-400', 'light' => 'bg-orange-50 dark:bg-orange-950','text' => 'text-orange-700 dark:text-orange-300'],
        'canceled' => ['bg' => 'bg-gray-400',   'light' => 'bg-gray-100 dark:bg-gray-800',   'text' => 'text-gray-600 dark:text-gray-400'],
        'pending'  => ['bg' => 'bg-gray-300',   'light' => 'bg-gray-50 dark:bg-gray-900',    'text' => 'text-gray-500 dark:text-gray-400'],
        'queued'   => ['bg' => 'bg-indigo-400', 'light' => 'bg-indigo-50 dark:bg-indigo-950','text' => 'text-indigo-700 dark:text-indigo-300'],
        'sending'  => ['bg' => 'bg-yellow-400', 'light' => 'bg-yellow-50 dark:bg-yellow-950','text' => 'text-yellow-700 dark:text-yellow-300'],
    ];

    $s       = $metrics['status'];
    $total   = max($s['total'], 1); // avoid div/0 in progress bars
    $inProg  = ($s['queued'] ?? 0) + ($s['sending'] ?? 0) + ($s['pending'] ?? 0);
@endphp

<div class="flex flex-col gap-4">

    {{-- ── Primary counters ──────────────────────────────────────── --}}
    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                @lang('admin::app.commercial.campaigns.metrics.title')
            </p>

            @if ($inProg > 0)
                <span class="flex items-center gap-1.5 text-xs text-yellow-600 dark:text-yellow-400">
                    <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    @lang('admin::app.commercial.campaigns.metrics.in-progress', ['count' => $inProg])
                </span>
            @endif
        </div>

        {{-- Main stat cards --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach (['sent', 'failed', 'skipped', 'canceled'] as $st)
                @php $c = $statusColors[$st]; @endphp
                <div class="rounded-lg p-3 text-center {{ $c['light'] }}">
                    <div class="text-2xl font-bold {{ $c['text'] }}">
                        {{ number_format($s[$st] ?? 0) }}
                    </div>
                    <div class="mt-0.5 text-xs {{ $c['text'] }}">
                        @lang('admin::app.commercial.campaigns.deliveries.statuses.' . $st)
                    </div>
                </div>
            @endforeach
        </div>

        {{-- In-progress row --}}
        @if ($inProg > 0)
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach (['pending', 'queued', 'sending'] as $st)
                    @if (($s[$st] ?? 0) > 0)
                        @php $c = $statusColors[$st]; @endphp
                        <div class="rounded-lg p-2 text-center {{ $c['light'] }}">
                            <div class="text-lg font-bold {{ $c['text'] }}">{{ number_format($s[$st]) }}</div>
                            <div class="text-xs {{ $c['text'] }}">@lang('admin::app.commercial.campaigns.deliveries.statuses.' . $st)</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Progress bar --}}
        @if ($s['total'] > 0)
            <div class="mt-4">
                <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    @foreach (['sent', 'failed', 'skipped', 'canceled', 'queued', 'sending', 'pending'] as $st)
                        @if (($s[$st] ?? 0) > 0)
                            <div
                                class="{{ $statusColors[$st]['bg'] }} h-full"
                                style="width: {{ round(($s[$st] / $total) * 100, 1) }}%"
                                title="{{ trans('admin::app.commercial.campaigns.deliveries.statuses.' . $st) }}: {{ $s[$st] }}"
                            ></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-400 dark:text-gray-500">
                    <span>0</span>
                    <span>{{ number_format($s['total']) }} @lang('admin::app.commercial.campaigns.metrics.total-label')</span>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

        {{-- ── By channel ──────────────────────────────────────────── --}}
        @if (!empty($metrics['channels']))
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    @lang('admin::app.commercial.campaigns.metrics.by-channel')
                </p>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">
                            <th class="pb-2 text-left">@lang('admin::app.commercial.campaigns.metrics.channel')</th>
                            <th class="pb-2 text-right">@lang('admin::app.commercial.campaigns.metrics.total')</th>
                            <th class="pb-2 text-right text-green-600 dark:text-green-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.sent')</th>
                            <th class="pb-2 text-right text-red-600 dark:text-red-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.failed')</th>
                            <th class="pb-2 text-right text-orange-500 dark:text-orange-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.skipped')</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach ($metrics['channels'] as $channel => $counts)
                            <tr>
                                <td class="py-1.5 font-medium text-gray-700 dark:text-gray-200">
                                    @lang('admin::app.commercial.campaigns.channels.' . $channel)
                                </td>
                                <td class="py-1.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($counts['total']) }}</td>
                                <td class="py-1.5 text-right text-green-600 dark:text-green-400">{{ number_format($counts['sent'] ?? 0) }}</td>
                                <td class="py-1.5 text-right text-red-600 dark:text-red-400">{{ number_format($counts['failed'] ?? 0) }}</td>
                                <td class="py-1.5 text-right text-orange-500 dark:text-orange-400">{{ number_format($counts['skipped'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ── By provider ─────────────────────────────────────────── --}}
        @if (!empty($metrics['providers']))
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    @lang('admin::app.commercial.campaigns.metrics.by-provider')
                </p>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">
                            <th class="pb-2 text-left">@lang('admin::app.commercial.campaigns.metrics.provider')</th>
                            <th class="pb-2 text-right">@lang('admin::app.commercial.campaigns.metrics.total')</th>
                            <th class="pb-2 text-right text-green-600 dark:text-green-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.sent')</th>
                            <th class="pb-2 text-right text-red-600 dark:text-red-400">@lang('admin::app.commercial.campaigns.deliveries.statuses.failed')</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach ($metrics['providers'] as $provider => $counts)
                            <tr>
                                <td class="py-1.5 font-medium text-gray-700 dark:text-gray-200">{{ $provider }}</td>
                                <td class="py-1.5 text-right text-gray-600 dark:text-gray-300">{{ number_format($counts['total']) }}</td>
                                <td class="py-1.5 text-right text-green-600 dark:text-green-400">{{ number_format($counts['sent'] ?? 0) }}</td>
                                <td class="py-1.5 text-right text-red-600 dark:text-red-400">{{ number_format($counts['failed'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Recent errors ────────────────────────────────────────── --}}
    @if ($metrics['errors']->isNotEmpty())
        <div class="box-shadow rounded-lg border border-red-100 bg-white p-4 dark:border-red-900 dark:bg-gray-900">
            <p class="mb-3 text-sm font-semibold text-red-700 dark:text-red-400">
                @lang('admin::app.commercial.campaigns.metrics.recent-errors')
            </p>

            <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($metrics['errors'] as $err)
                    <div class="py-2 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium text-gray-700 dark:text-gray-200">
                                {{ $err->recipient_name ?? ($err->recipient_email ?? $err->recipient_phone ?? '—') }}
                            </div>
                            <div class="shrink-0 text-gray-400 dark:text-gray-500">
                                {{ $err->channel }} / {{ $err->provider }}
                                @if ($err->failed_at)
                                    · {{ \Carbon\Carbon::parse($err->failed_at)->format('d/m H:i') }}
                                @endif
                            </div>
                        </div>
                        <div class="mt-0.5 text-red-600 dark:text-red-400">{{ $err->failure_reason }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
