@php
    $statusColors = [
        'pending'  => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        'queued'   => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
        'sending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
        'sent'     => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'failed'   => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        'skipped'  => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
        'canceled' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    ];
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">
        @lang('admin::app.commercial.campaigns.deliveries.stats-title')
    </p>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach (['sent', 'pending', 'failed', 'skipped'] as $status)
            <div class="rounded-lg px-3 py-2 text-center {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-600' }}">
                <div class="text-xl font-bold">{{ number_format($stats[$status] ?? 0) }}</div>
                <div class="text-xs">@lang('admin::app.commercial.campaigns.deliveries.statuses.' . $status)</div>
            </div>
        @endforeach
    </div>

    @if (($stats['queued'] ?? 0) + ($stats['sending'] ?? 0) > 0)
        <div class="mt-2 flex items-center gap-2 text-xs text-yellow-600 dark:text-yellow-400">
            <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            @lang('admin::app.commercial.campaigns.deliveries.sending-in-progress', [
                'count' => ($stats['queued'] ?? 0) + ($stats['sending'] ?? 0)
            ])
        </div>
    @endif
</div>
