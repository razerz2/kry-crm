@php
    $campaign = $campaign ?? null;
    $timezoneValue = old('timezone', $campaign->timezone ?? config('app.timezone'));
    $executionType = old('execution_type', $campaign->execution_type ?? 'manual');
    $recurrenceType = old('recurrence_type', $campaign->recurrence_type ?? '');

    $formatDateTime = function ($value) use ($timezoneValue) {
        if (! $value) {
            return '';
        }

        return \Carbon\Carbon::parse($value)->setTimezone($timezoneValue)->format('Y-m-d H:i:s');
    };

    $runAtValue = old('run_at', $formatDateTime($campaign?->run_at));
    $startsAtValue = old('starts_at', $formatDateTime($campaign?->starts_at));
    $endsAtValue = old('ends_at', $formatDateTime($campaign?->ends_at));

    $daysOfWeek = old('days_of_week', $campaign?->days_of_week ?? []);
    $daysOfWeek = is_array($daysOfWeek) ? $daysOfWeek : [];

    $dayOfMonth = old('day_of_month', $campaign?->day_of_month);
    $intervalValue = old('interval_value', $campaign?->interval_value);
    $intervalUnit = old('interval_unit', $campaign?->interval_unit);
    $maxRuns = old('max_runs', $campaign?->max_runs);

    $timeOfDay = old('time_of_day', $campaign?->time_of_day ? substr((string) $campaign->time_of_day, 0, 5) : '');
    $windowStart = old('window_start_time', $campaign?->window_start_time ? substr((string) $campaign->window_start_time, 0, 5) : '');
    $windowEnd = old('window_end_time', $campaign?->window_end_time ? substr((string) $campaign->window_end_time, 0, 5) : '');
@endphp

<div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 js-campaign-schedule">
    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
        @lang('admin::app.commercial.campaigns.schedule.title')
    </p>

    <div class="grid grid-cols-1 gap-4">
        <div>
            <x-admin::form.control-group>
                <x-admin::form.control-group.label class="required">
                    @lang('admin::app.commercial.campaigns.schedule.execution-type')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control type="select" name="execution_type" :value="$executionType">
                    <option value="manual" {{ $executionType === 'manual' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.execution-types.manual')
                    </option>
                    <option value="once" {{ $executionType === 'once' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.execution-types.once')
                    </option>
                    <option value="recurring" {{ $executionType === 'recurring' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.execution-types.recurring')
                    </option>
                    <option value="windowed_recurring" {{ $executionType === 'windowed_recurring' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.execution-types.windowed-recurring')
                    </option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>

        <div>
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.timezone')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="text"
                    name="timezone"
                    :value="$timezoneValue"
                    :placeholder="trans('admin::app.commercial.campaigns.schedule.timezone-placeholder')"
                />
            </x-admin::form.control-group>
        </div>
    </div>

    <div class="mt-4 js-once-only">
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.commercial.campaigns.schedule.run-at')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="datetime"
                name="run_at"
                :value="$runAtValue"
            />
        </x-admin::form.control-group>
    </div>

    <div class="mt-4 space-y-4 js-recurring-only">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.starts-at')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="datetime"
                    name="starts_at"
                    :value="$startsAtValue"
                />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.ends-at')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="datetime"
                    name="ends_at"
                    :value="$endsAtValue"
                />
            </x-admin::form.control-group>
        </div>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label class="required">
                @lang('admin::app.commercial.campaigns.schedule.recurrence-type')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control type="select" name="recurrence_type" :value="$recurrenceType">
                <option value="">@lang('admin::app.commercial.campaigns.schedule.select-recurrence')</option>
                <option value="daily" {{ $recurrenceType === 'daily' ? 'selected' : '' }}>
                    @lang('admin::app.commercial.campaigns.schedule.recurrence-types.daily')
                </option>
                <option value="weekly" {{ $recurrenceType === 'weekly' ? 'selected' : '' }}>
                    @lang('admin::app.commercial.campaigns.schedule.recurrence-types.weekly')
                </option>
                <option value="monthly" {{ $recurrenceType === 'monthly' ? 'selected' : '' }}>
                    @lang('admin::app.commercial.campaigns.schedule.recurrence-types.monthly')
                </option>
                <option value="interval" {{ $recurrenceType === 'interval' ? 'selected' : '' }}>
                    @lang('admin::app.commercial.campaigns.schedule.recurrence-types.interval')
                </option>
            </x-admin::form.control-group.control>
        </x-admin::form.control-group>

        <div class="js-time-of-day-only">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.time-of-day')
                </x-admin::form.control-group.label>

                <input
                    type="time"
                    name="time_of_day"
                    value="{{ $timeOfDay }}"
                    class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                />
            </x-admin::form.control-group>
        </div>

        <div class="js-weekly-only">
            <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                @lang('admin::app.commercial.campaigns.schedule.days-of-week')
            </label>

            <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-300">
                @foreach ([
                    0 => trans('admin::app.commercial.campaigns.schedule.weekdays.sun'),
                    1 => trans('admin::app.commercial.campaigns.schedule.weekdays.mon'),
                    2 => trans('admin::app.commercial.campaigns.schedule.weekdays.tue'),
                    3 => trans('admin::app.commercial.campaigns.schedule.weekdays.wed'),
                    4 => trans('admin::app.commercial.campaigns.schedule.weekdays.thu'),
                    5 => trans('admin::app.commercial.campaigns.schedule.weekdays.fri'),
                    6 => trans('admin::app.commercial.campaigns.schedule.weekdays.sat'),
                ] as $value => $label)
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="days_of_week[]"
                            value="{{ $value }}"
                            {{ in_array((string) $value, array_map('strval', $daysOfWeek), true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600"
                        />
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="js-monthly-only">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.day-of-month')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="number"
                    name="day_of_month"
                    min="1"
                    max="31"
                    :value="$dayOfMonth"
                />
            </x-admin::form.control-group>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 js-interval-only">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.interval-value')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control
                    type="number"
                    name="interval_value"
                    min="1"
                    :value="$intervalValue"
                />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.interval-unit')
                </x-admin::form.control-group.label>

                <x-admin::form.control-group.control type="select" name="interval_unit" :value="$intervalUnit">
                    <option value="">@lang('admin::app.commercial.campaigns.schedule.select-interval-unit')</option>
                    <option value="minutes" {{ $intervalUnit === 'minutes' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.interval-units.minutes')
                    </option>
                    <option value="hours" {{ $intervalUnit === 'hours' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.interval-units.hours')
                    </option>
                    <option value="days" {{ $intervalUnit === 'days' ? 'selected' : '' }}>
                        @lang('admin::app.commercial.campaigns.schedule.interval-units.days')
                    </option>
                </x-admin::form.control-group.control>
            </x-admin::form.control-group>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 js-windowed-only">
            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.window-start')
                </x-admin::form.control-group.label>

                <input
                    type="time"
                    name="window_start_time"
                    value="{{ $windowStart }}"
                    class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                />
            </x-admin::form.control-group>

            <x-admin::form.control-group>
                <x-admin::form.control-group.label>
                    @lang('admin::app.commercial.campaigns.schedule.window-end')
                </x-admin::form.control-group.label>

                <input
                    type="time"
                    name="window_end_time"
                    value="{{ $windowEnd }}"
                    class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                />
            </x-admin::form.control-group>
        </div>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.commercial.campaigns.schedule.max-runs')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="number"
                name="max_runs"
                min="1"
                :value="$maxRuns"
            />
        </x-admin::form.control-group>
    </div>

    @if ($campaign)
        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <div>
                <strong>@lang('admin::app.commercial.campaigns.schedule.next-run-at'):</strong>
                {{ $campaign->next_run_at ? $campaign->next_run_at->setTimezone($campaign->timezone ?? config('app.timezone'))->format('d/m/Y H:i') : '-' }}
            </div>
            <div class="mt-1">
                <strong>@lang('admin::app.commercial.campaigns.schedule.last-run-at'):</strong>
                {{ $campaign->last_run_at ? $campaign->last_run_at->setTimezone($campaign->timezone ?? config('app.timezone'))->format('d/m/Y H:i') : '-' }}
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function getValue(root, name, fallback) {
                    const field = root.querySelector('[name="' + name + '"]');

                    return field ? field.value : fallback;
                }

                function toggle(el, visible) {
                    if (!el) return;
                    el.style.display = visible ? '' : 'none';
                }

                function refreshSchedule(root) {
                    const execution = getValue(root, 'execution_type', 'manual');
                    const recurrence = getValue(root, 'recurrence_type', '');

                    toggle(root.querySelector('.js-once-only'), execution === 'once');
                    toggle(root.querySelector('.js-recurring-only'), execution === 'recurring' || execution === 'windowed_recurring');
                    toggle(root.querySelector('.js-windowed-only'), execution === 'windowed_recurring');

                    const isRecurring = execution === 'recurring' || execution === 'windowed_recurring';
                    toggle(root.querySelector('.js-time-of-day-only'), isRecurring && ['daily', 'weekly', 'monthly'].includes(recurrence));
                    toggle(root.querySelector('.js-weekly-only'), isRecurring && recurrence === 'weekly');
                    toggle(root.querySelector('.js-monthly-only'), isRecurring && recurrence === 'monthly');
                    toggle(root.querySelector('.js-interval-only'), isRecurring && recurrence === 'interval');
                }

                function bindRoot(root) {
                    if (!root || root.dataset.scheduleBound === '1') {
                        return;
                    }

                    root.dataset.scheduleBound = '1';
                    refreshSchedule(root);
                }

                function refreshAll() {
                    document.querySelectorAll('.js-campaign-schedule').forEach(bindRoot);
                }

                document.addEventListener('change', function (event) {
                    const root = event.target.closest('.js-campaign-schedule');

                    if (!root) {
                        return;
                    }

                    refreshSchedule(root);
                });

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', refreshAll);
                } else {
                    refreshAll();
                }

                window.addEventListener('load', function () {
                    setTimeout(refreshAll, 0);
                });
            })();
        </script>
    @endpush
@endonce
