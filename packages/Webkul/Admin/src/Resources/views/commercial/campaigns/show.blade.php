<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.show.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        {{-- Header --}}
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    {{ $campaign->name }}
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    @php
                        $statusClass = match($campaign->status) {
                            'draft'          => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                            'ready'          => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            'sending'        => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                            'sent'           => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'partially_sent' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                            'failed'         => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            default          => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        };
                    @endphp
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                        @lang('admin::app.commercial.campaigns.statuses.' . $campaign->status)
                    </span>
                    <span>@lang('admin::app.commercial.campaigns.channels.' . $campaign->channel)</span>
                    <span>@lang('admin::app.commercial.campaigns.show.created-at', ['date' => $campaign->created_at->format('d/m/Y')])</span>
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.index') }}" class="transparent-button">
                    @lang('admin::app.commercial.campaigns.show.back-btn')
                </a>

                @if (bouncer()->hasPermission('commercial.campaigns.dispatch') && ($campaign->total_deliveries > 0 || $campaign->isLocked()))
                    <a href="{{ route('admin.commercial.campaigns.deliveries', $campaign->id) }}" class="transparent-button">
                        @lang('admin::app.commercial.campaigns.dispatch.view-deliveries')
                    </a>
                @endif

                @if (bouncer()->hasPermission('commercial.campaigns.create'))
                    <form action="{{ route('admin.commercial.campaigns.duplicate', $campaign->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="transparent-button">
                            @lang('admin::app.commercial.campaigns.duplicate.btn')
                        </button>
                    </form>
                @endif

                @if (bouncer()->hasPermission('commercial.campaigns.edit') && ! $campaign->isLocked() && ! $campaign->isArchived())
                    <a href="{{ route('admin.commercial.campaigns.edit', $campaign->id) }}" class="primary-button">
                        @lang('admin::app.commercial.campaigns.show.edit-btn')
                    </a>
                @endif
            </div>
        </div>

        {{-- Full metrics panel (after first dispatch) --}}
        @if (!empty($metrics))
            @include('admin::commercial.campaigns.partials.campaign-metrics', ['metrics' => $metrics])
        @endif

        {{-- Campaign Details + Stats --}}
        <div class="flex gap-2.5 max-xl:flex-wrap">
            {{-- Left: Details --}}
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                {{-- Info Card --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.commercial.campaigns.show.details')
                    </p>

                    @if ($campaign->description)
                        <div class="mb-4">
                            <div class="mb-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.create.description')
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $campaign->description }}</p>
                        </div>
                    @endif

                    @if ($campaign->subject)
                        <div class="mb-4">
                            <div class="mb-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.create.subject')
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $campaign->subject }}</p>
                        </div>
                    @endif

                    @if ($campaign->message_body)
                        <div class="mb-4">
                            <div class="mb-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.create.message-body')
                            </div>
                            <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300 whitespace-pre-wrap">{{ $campaign->message_body }}</div>
                        </div>
                    @endif

                    @if ($campaign->creator)
                        <div class="mt-4 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            @lang('admin::app.commercial.campaigns.show.created-by', ['user' => $campaign->creator->name])
                        </div>
                    @endif
                </div>

                {{-- Audience Table --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.show.audience')
                        </p>

                        @if ($campaign->hasAudience())
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.show.audience-generated', [
                                    'date' => $campaign->audience_generated_at->format('d/m/Y H:i'),
                                ])
                            </span>
                        @endif
                    </div>

                    @if ($audience->isNotEmpty())
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">#</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.name')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.type')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.organization')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.email')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.phone')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.channels')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.products')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.commercial-statuses')</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @foreach ($audience as $i => $member)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <td class="px-4 py-2 text-gray-400 dark:text-gray-500">{{ $i + 1 }}</td>
                                            <td class="px-4 py-2 font-medium text-gray-800 dark:text-white">{{ $member->display_name }}</td>
                                            <td class="px-4 py-2">
                                                <span class="rounded-full px-2 py-0.5 text-xs
                                                    {{ $member->entity_type === 'person' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' }}">
                                                    @lang('admin::app.commercial.campaigns.audience.entity-' . $member->entity_type)
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $member->organization_name ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $member->email ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $member->phone ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                                {{ implode(', ', $member->available_channels ?? []) ?: '—' }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                                {{ implode(', ', $member->crm_products ?? []) ?: '—' }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                                {{ implode(', ', $member->commercial_statuses ?? []) ?: '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.show.no-audience')
                            </p>

                            @if (bouncer()->hasPermission('commercial.campaigns.edit'))
                                <a href="{{ route('admin.commercial.campaigns.edit', $campaign->id) }}" class="mt-3 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400">
                                    @lang('admin::app.commercial.campaigns.show.go-to-edit')
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: Stats + Filters Summary --}}
            <div class="flex w-[300px] max-w-full flex-col gap-2 max-sm:w-full">
                {{-- Stats --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.commercial.campaigns.show.stats')
                    </p>

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                            <span class="text-sm text-gray-600 dark:text-gray-300">@lang('admin::app.commercial.campaigns.stats.total')</span>
                            <span class="text-lg font-bold text-gray-800 dark:text-white">{{ number_format($campaign->total_audience) }}</span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-blue-50 px-3 py-2 dark:bg-blue-950">
                            <span class="text-sm text-blue-700 dark:text-blue-300">@lang('admin::app.commercial.campaigns.stats.with-email')</span>
                            <span class="text-lg font-bold text-blue-700 dark:text-blue-300">{{ number_format($campaign->total_with_email) }}</span>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2 dark:bg-green-950">
                            <span class="text-sm text-green-700 dark:text-green-300">@lang('admin::app.commercial.campaigns.stats.with-phone')</span>
                            <span class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format($campaign->total_with_phone) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Active Filters Summary --}}
                @php $f = $campaign->filters_json ?? []; @endphp
                @if (!empty($f))
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.show.active-filters')
                        </p>

                        <dl class="flex flex-col gap-2 text-sm">
                            @if (!empty($f['entity_type']) && $f['entity_type'] !== 'both')
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.filters.entity-type')</dt>
                                    <dd class="font-medium text-gray-700 dark:text-gray-200">{{ $f['entity_type'] }}</dd>
                                </div>
                            @endif

                            @if (!empty($f['crm_product_ids']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.filters.crm-products')</dt>
                                    <dd class="font-medium text-gray-700 dark:text-gray-200">
                                        {{ $products->whereIn('id', $f['crm_product_ids'])->pluck('name')->implode(', ') }}
                                    </dd>
                                </div>
                            @endif

                            @if (!empty($f['commercial_statuses']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.filters.commercial-statuses')</dt>
                                    <dd class="font-medium text-gray-700 dark:text-gray-200">{{ implode(', ', $f['commercial_statuses']) }}</dd>
                                </div>
                            @endif

                            @if (!empty($f['segment']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.filters.segment')</dt>
                                    <dd class="font-medium text-gray-700 dark:text-gray-200">{{ $f['segment'] }}</dd>
                                </div>
                            @endif

                            @if (!empty($f['only_with_email']))
                                <div class="text-blue-600 dark:text-blue-400">✓ @lang('admin::app.commercial.campaigns.filters.only-with-email')</div>
                            @endif

                            @if (!empty($f['only_with_phone']))
                                <div class="text-green-600 dark:text-green-400">✓ @lang('admin::app.commercial.campaigns.filters.only-with-phone')</div>
                            @endif
                        </dl>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin::layouts>
