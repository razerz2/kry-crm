<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.commercial.campaigns.edit.title')
    </x-slot>

    <div
        x-data="campaignEditor()"
        x-init="init()"
        class="flex flex-col gap-4"
    >
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
                            'scheduled'      => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                            'running'        => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                            'paused'         => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                            'completed'      => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'canceled'       => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
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
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <a href="{{ route('admin.commercial.campaigns.index') }}" class="transparent-button">
                    @lang('admin::app.commercial.campaigns.edit.back-btn')
                </a>

                <a href="{{ route('admin.commercial.campaigns.show', $campaign->id) }}" class="transparent-button">
                    @lang('admin::app.commercial.campaigns.edit.view-btn')
                </a>

                {{-- Duplicate --}}
                @if (bouncer()->hasPermission('commercial.campaigns.create'))
                    <form action="{{ route('admin.commercial.campaigns.duplicate', $campaign->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="transparent-button">
                            @lang('admin::app.commercial.campaigns.duplicate.btn')
                        </button>
                    </form>
                @endif

                {{-- Mark Ready / Revert to Draft --}}
                @if (bouncer()->hasPermission('commercial.campaigns.dispatch'))
                    @if ($campaign->isDraft())
                        <form action="{{ route('admin.commercial.campaigns.mark_ready', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="primary-button">
                                @lang('admin::app.commercial.campaigns.dispatch.mark-ready-btn')
                            </button>
                        </form>
                    @elseif ($campaign->isReady())
                        <form action="{{ route('admin.commercial.campaigns.mark_draft', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="transparent-button">
                                @lang('admin::app.commercial.campaigns.dispatch.mark-draft-btn')
                            </button>
                        </form>
                    @endif

                    @if ($campaign->execution_type === 'manual' && $campaign->canDispatch())
                        <form
                            action="{{ route('admin.commercial.campaigns.dispatch', $campaign->id) }}"
                            method="POST"
                            onsubmit="return confirm('{{ trans('admin::app.commercial.campaigns.dispatch.confirm', ['count' => $campaign->total_audience]) }}')"
                        >
                            @csrf
                            <button type="submit" class="primary-button !bg-green-600 hover:!bg-green-700">
                                @lang('admin::app.commercial.campaigns.dispatch.btn')
                            </button>
                        </form>
                    @endif

                    @if (in_array($campaign->status, ['ready', 'scheduled', 'paused'], true))
                        <form action="{{ route('admin.commercial.campaigns.run_now', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="secondary-button">
                                @lang('admin::app.commercial.campaigns.schedule.run-now-btn')
                            </button>
                        </form>
                    @endif

                    @if ($campaign->execution_type !== 'manual' && in_array($campaign->status, ['ready', 'paused', 'completed'], true))
                        <form action="{{ route('admin.commercial.campaigns.schedule', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="primary-button">
                                @lang('admin::app.commercial.campaigns.schedule.schedule-btn')
                            </button>
                        </form>
                    @endif

                    @if ($campaign->execution_type !== 'manual')
                        <form action="{{ route('admin.commercial.campaigns.recalculate_next_run', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="transparent-button">
                                @lang('admin::app.commercial.campaigns.schedule.recalculate-next-btn')
                            </button>
                        </form>
                    @endif

                    @if ($campaign->isScheduled())
                        <form action="{{ route('admin.commercial.campaigns.pause', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="transparent-button">
                                @lang('admin::app.commercial.campaigns.schedule.pause-btn')
                            </button>
                        </form>
                    @endif

                    @if ($campaign->isPaused())
                        <form action="{{ route('admin.commercial.campaigns.resume', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="primary-button">
                                @lang('admin::app.commercial.campaigns.schedule.resume-btn')
                            </button>
                        </form>
                    @endif

                    @if (! $campaign->isCanceled() && ! $campaign->isCompleted())
                        <form action="{{ route('admin.commercial.campaigns.cancel', $campaign->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="transparent-button">
                                @lang('admin::app.commercial.campaigns.schedule.cancel-btn')
                            </button>
                        </form>
                    @endif
                @endif

                {{-- View deliveries --}}
                @if ($campaign->isLocked() || $campaign->total_deliveries > 0)
                    <a href="{{ route('admin.commercial.campaigns.deliveries', $campaign->id) }}" class="primary-button">
                        @lang('admin::app.commercial.campaigns.dispatch.view-deliveries')
                    </a>
                @endif

                @if (bouncer()->hasPermission('commercial.executions'))
                    <a href="{{ route('admin.commercial.executions.index', ['campaign_id' => $campaign->id]) }}" class="transparent-button">
                        @lang('admin::app.commercial.executions.index.menu-shortcut')
                    </a>
                @endif
            </div>
        </div>

        {{-- Archived banner --}}
        @if ($campaign->isArchived())
            <div class="rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                @lang('admin::app.commercial.campaigns.guard.campaign-archived-banner')
            </div>
        @endif

        {{-- Locked (sent/failed/sending) banner --}}
        @if ($campaign->isLocked())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                {{ trans('admin::app.commercial.campaigns.dispatch.locked-banner', ['status' => trans('admin::app.commercial.campaigns.statuses.' . $campaign->status)]) }}
            </div>
        @endif

        {{-- Audience stale warning --}}
        @if ($audienceStale ?? false)
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-700 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-300">
                @lang('admin::app.commercial.campaigns.guard.audience-stale-warning')
            </div>
        @endif

        {{-- Readiness issues (shown when campaign is draft) --}}
        @if (!empty($readinessIssues) && $campaign->isDraft())
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 dark:border-orange-800 dark:bg-orange-950">
                <p class="mb-1 text-sm font-semibold text-orange-700 dark:text-orange-300">
                    @lang('admin::app.commercial.campaigns.guard.not-ready-title')
                </p>
                <ul class="list-inside list-disc text-xs text-orange-600 dark:text-orange-400">
                    @foreach ($readinessIssues as $issue)
                        <li>@lang('admin::app.commercial.campaigns.guard.' . str_replace(['.', ':'], '-', $issue))</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Audience Stats Bar --}}
        @if ($campaign->hasAudience())
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($campaign->total_audience) }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.total')</div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($campaign->total_with_email) }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.with-email')</div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($campaign->total_with_phone) }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.with-phone')</div>
                </div>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-300">
                @lang('admin::app.commercial.campaigns.edit.audience-frozen-at', [
                    'date' => $campaign->audience_generated_at->format('d/m/Y H:i'),
                ])
            </div>
        @endif

        {{-- Delivery stats (shown after first dispatch) --}}
        @if (($stats['total'] ?? 0) > 0)
            @include('admin::commercial.campaigns.partials.delivery-stats', ['stats' => $stats])
        @endif

        @if (($recentRuns ?? collect())->isNotEmpty())
            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.commercial.campaigns.schedule.recent-runs')
                </p>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">#</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">@lang('admin::app.commercial.campaigns.schedule.status')</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">@lang('admin::app.commercial.campaigns.schedule.scheduled-for')</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">@lang('admin::app.commercial.campaigns.schedule.finished-at')</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">@lang('admin::app.commercial.campaigns.deliveries.stats-title')</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @foreach ($recentRuns as $run)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-800 dark:text-white">
                                        @if (bouncer()->hasPermission('commercial.executions.view'))
                                            <a href="{{ route('admin.commercial.executions.show', $run->id) }}" class="text-brandColor hover:underline">
                                                #{{ $run->id }}
                                            </a>
                                        @else
                                            #{{ $run->id }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                        @lang('admin::app.commercial.campaigns.schedule.run-statuses.' . $run->status)
                                    </td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                        {{ $run->scheduled_for ? $run->scheduled_for->setTimezone($campaign->timezone ?? config('app.timezone'))->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                        {{ $run->finished_at ? $run->finished_at->setTimezone($campaign->timezone ?? config('app.timezone'))->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">
                                        {{ number_format($run->total_deliveries) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="flex gap-2.5 max-xl:flex-wrap">
            {{-- Left: Campaign Form --}}
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                @php $readOnly = $campaign->isLocked() || $campaign->isArchived(); @endphp

                <x-admin::form
                    :action="route('admin.commercial.campaigns.update', $campaign->id)"
                    method="PUT"
                >
                    <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.edit.general')
                        </p>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.commercial.campaigns.create.name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="name"
                                    rules="required"
                                    :value="old('name', $campaign->name)"
                                    :label="trans('admin::app.commercial.campaigns.create.name')"
                                />

                                <x-admin::form.control-group.error control-name="name" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.description')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="description"
                                    :value="old('description', $campaign->description)"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.commercial.campaigns.create.channel')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="channel"
                                    rules="required"
                                    :label="trans('admin::app.commercial.campaigns.create.channel')"
                                >
                                    <option value="email" {{ old('channel', $campaign->channel) === 'email' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.channels.email')
                                    </option>
                                    <option value="whatsapp" {{ old('channel', $campaign->channel) === 'whatsapp' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.channels.whatsapp')
                                    </option>
                                    <option value="both" {{ old('channel', $campaign->channel) === 'both' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.channels.both')
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="channel" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.edit.status')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="status"
                                >
                                    <option value="draft" {{ old('status', $campaign->status) === 'draft' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.draft')
                                    </option>
                                    <option value="ready" {{ old('status', $campaign->status) === 'ready' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.ready')
                                    </option>
                                    <option value="scheduled" {{ old('status', $campaign->status) === 'scheduled' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.scheduled')
                                    </option>
                                    <option value="paused" {{ old('status', $campaign->status) === 'paused' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.paused')
                                    </option>
                                    <option value="completed" {{ old('status', $campaign->status) === 'completed' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.completed')
                                    </option>
                                    <option value="canceled" {{ old('status', $campaign->status) === 'canceled' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.canceled')
                                    </option>
                                    <option value="archived" {{ old('status', $campaign->status) === 'archived' ? 'selected' : '' }}>
                                        @lang('admin::app.commercial.campaigns.statuses.archived')
                                    </option>
                                </x-admin::form.control-group.control>
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.subject')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="subject"
                                    :value="old('subject', $campaign->subject)"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="mb-4">
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.commercial.campaigns.create.message-body')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="message_body"
                                    rows="6"
                                    :value="old('message_body', $campaign->message_body)"
                                />
                            </x-admin::form.control-group>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            @if ($readOnly)
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    @lang('admin::app.commercial.campaigns.guard.form-read-only')
                                </p>
                            @else
                                <button type="submit" class="primary-button">
                                    @lang('admin::app.commercial.campaigns.edit.save-btn')
                                </button>
                            @endif
                        </div>
                    </div>

                    @include('admin::commercial.campaigns.partials.schedule-fields', ['campaign' => $campaign])
                </x-admin::form>

                {{-- Template Preview --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.template.preview-title')
                        </p>

                        <button
                            type="button"
                            @click="generateTemplatePreview()"
                            :disabled="templatePreviewLoading"
                            class="secondary-button"
                        >
                            <span x-show="!templatePreviewLoading">@lang('admin::app.commercial.campaigns.template.preview-btn')</span>
                            <span x-show="templatePreviewLoading">@lang('admin::app.commercial.campaigns.edit.preview-loading')</span>
                        </button>
                    </div>

                    <div x-show="templatePreviewDone" class="flex flex-col gap-3">
                        <div x-show="templatePreviewIsDummy" class="rounded bg-yellow-50 px-3 py-2 text-xs text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300">
                            @lang('admin::app.commercial.campaigns.template.preview-dummy-notice')
                        </div>

                        <template x-if="templatePreview.subject">
                            <div>
                                <div class="mb-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    @lang('admin::app.commercial.campaigns.create.subject')
                                </div>
                                <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300" x-text="templatePreview.subject"></div>
                            </div>
                        </template>

                        <div>
                            <div class="mb-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.create.message-body')
                            </div>
                            <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300 whitespace-pre-wrap" x-text="templatePreview.body"></div>
                        </div>

                        <details class="text-xs text-gray-500 dark:text-gray-400">
                            <summary class="cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">
                                @lang('admin::app.commercial.campaigns.template.preview-sample-label')
                            </summary>
                            <div class="mt-2 grid grid-cols-2 gap-1 rounded-md bg-gray-50 p-2 dark:bg-gray-800">
                                <template x-for="[k, v] in Object.entries(templatePreview.sample ?? {})" :key="k">
                                    <div class="flex gap-1">
                                        <span class="font-mono text-gray-400 dark:text-gray-500" x-text="'{{' + k + '}}'"></span>
                                        <span class="truncate text-gray-600 dark:text-gray-300" x-text="v || '—'"></span>
                                    </div>
                                </template>
                            </div>
                        </details>
                    </div>
                </div>

                {{-- Audience Preview Section --}}
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.commercial.campaigns.edit.audience-section')
                        </p>

                        <div class="flex items-center gap-2">
                            {{-- Preview Button --}}
                            <button
                                type="button"
                                @click="generatePreview()"
                                :disabled="previewLoading"
                                class="secondary-button"
                            >
                                <span x-show="!previewLoading">@lang('admin::app.commercial.campaigns.edit.preview-btn')</span>
                                <span x-show="previewLoading">@lang('admin::app.commercial.campaigns.edit.preview-loading')</span>
                            </button>

                            {{-- Freeze Audience --}}
                            @if (bouncer()->hasPermission('commercial.campaigns.edit'))
                                <form
                                    action="{{ route('admin.commercial.campaigns.freeze_audience', $campaign->id) }}"
                                    method="POST"
                                    onsubmit="return confirm('@lang('admin::app.commercial.campaigns.edit.freeze-confirm')')"
                                >
                                    @csrf
                                    <button type="submit" class="primary-button">
                                        @lang('admin::app.commercial.campaigns.edit.freeze-btn')
                                    </button>
                                </form>

                                @if ($campaign->hasAudience())
                                    <form
                                        action="{{ route('admin.commercial.campaigns.recalculate_audience', $campaign->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('@lang('admin::app.commercial.campaigns.edit.recalculate-confirm')')"
                                    >
                                        @csrf
                                        <button type="submit" class="transparent-button">
                                            @lang('admin::app.commercial.campaigns.edit.recalculate-btn')
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Preview Results --}}
                    <div x-show="previewDone" class="mb-4">
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="rounded-lg bg-gray-50 p-3 text-center dark:bg-gray-800">
                                <div class="text-xl font-bold text-gray-800 dark:text-white" x-text="preview.stats.total ?? 0"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.total')</div>
                            </div>
                            <div class="rounded-lg bg-blue-50 p-3 text-center dark:bg-blue-950">
                                <div class="text-xl font-bold text-blue-700 dark:text-blue-300" x-text="preview.stats.with_email ?? 0"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.with-email')</div>
                            </div>
                            <div class="rounded-lg bg-green-50 p-3 text-center dark:bg-green-950">
                                <div class="text-xl font-bold text-green-700 dark:text-green-300" x-text="preview.stats.with_phone ?? 0"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.stats.with-phone')</div>
                            </div>
                        </div>

                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            @lang('admin::app.commercial.campaigns.edit.preview-sample')
                        </p>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.name')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.type')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.email')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.phone')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.channels')</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    <template x-for="item in preview.items" :key="item.entity_type + item.entity_id">
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-800 dark:text-white" x-text="item.display_name"></td>
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.entity_type"></td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300" x-text="item.email ?? '—'"></td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300" x-text="item.phone ?? '—'"></td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300" x-text="(item.available_channels ?? []).join(', ')"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Frozen Audience Preview --}}
                    @if ($audience->isNotEmpty())
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            @lang('admin::app.commercial.campaigns.edit.frozen-audience-preview', ['count' => $audience->count(), 'total' => $campaign->total_audience])
                        </p>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.name')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.organization')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.email')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.phone')</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">@lang('admin::app.commercial.campaigns.audience.channels')</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @foreach ($audience as $member)
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-800 dark:text-white">{{ $member->display_name }}</td>
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $member->organization_name ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $member->email ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $member->phone ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                                {{ implode(', ', $member->available_channels ?? []) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($campaign->total_audience > $audience->count())
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                @lang('admin::app.commercial.campaigns.edit.showing-partial', [
                                    'shown' => $audience->count(),
                                    'total' => $campaign->total_audience,
                                ])
                                <a href="{{ route('admin.commercial.campaigns.show', $campaign->id) }}" class="text-blue-600 hover:underline dark:text-blue-400">
                                    @lang('admin::app.commercial.campaigns.edit.view-all-link')
                                </a>
                            </p>
                        @endif
                    @elseif (! $campaign->hasAudience())
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @lang('admin::app.commercial.campaigns.edit.no-audience')
                        </p>
                    @endif
                </div>
            </div>

            {{-- Right: Filters + Template Variables --}}
            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                @include('admin::commercial.campaigns.partials.filters', [
                    'filters'  => $campaign->filters_json ?? [],
                    'products' => $products,
                ])

                <div class="rounded-lg border border-gray-200 bg-white p-4 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    @lang('admin::app.commercial.campaigns.edit.filters-hint')
                </div>

                @include('admin::commercial.campaigns.partials.template-variables')
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function campaignEditor() {
                return {
                    previewLoading: false,
                    previewDone: false,
                    preview: { stats: {}, items: [] },

                    templatePreviewLoading: false,
                    templatePreviewDone: false,
                    templatePreviewIsDummy: false,
                    templatePreview: { subject: '', body: '', sample: {} },

                    init() {},

                    generateTemplatePreview() {
                        this.templatePreviewLoading = true;
                        this.templatePreviewDone = false;

                        const formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');

                        const subjectEl = document.querySelector('[name="subject"]');
                        const bodyEl    = document.querySelector('[name="message_body"]');

                        if (subjectEl) formData.append('subject', subjectEl.value);
                        if (bodyEl)    formData.append('message_body', bodyEl.value);

                        fetch('{{ route('admin.commercial.campaigns.preview_template', $campaign->id) }}', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData,
                        })
                        .then(response => response.json())
                        .then(data => {
                            this.templatePreview      = data;
                            this.templatePreviewIsDummy = data.is_dummy ?? false;
                            this.templatePreviewDone  = true;
                            this.templatePreviewLoading = false;
                        })
                        .catch(() => {
                            this.templatePreviewLoading = false;
                            alert('@lang('admin::app.commercial.campaigns.template.preview-error')');
                        });
                    },

                    generatePreview() {
                        this.previewLoading = true;
                        this.previewDone = false;

                        const formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');

                        // Collect all filter inputs from the page
                        const filterInputs = document.querySelectorAll('[name^="filter_"]');
                        filterInputs.forEach(input => {
                            if (input.type === 'checkbox') {
                                if (input.checked) {
                                    formData.append(input.name, input.value);
                                }
                            } else if (input.tagName === 'SELECT' && input.multiple) {
                                Array.from(input.selectedOptions).forEach(opt => {
                                    formData.append(input.name, opt.value);
                                });
                            } else {
                                if (input.value) {
                                    formData.append(input.name, input.value);
                                }
                            }
                        });

                        formData.append('preview_limit', '20');

                        fetch('{{ route('admin.commercial.campaigns.preview_audience') }}', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: formData,
                        })
                        .then(response => response.json())
                        .then(data => {
                            this.preview = data;
                            this.previewDone = true;
                            this.previewLoading = false;
                        })
                        .catch(() => {
                            this.previewLoading = false;
                            alert('@lang('admin::app.commercial.campaigns.edit.preview-error')');
                        });
                    },
                };
            }
        </script>
    @endpush
</x-admin::layouts>
