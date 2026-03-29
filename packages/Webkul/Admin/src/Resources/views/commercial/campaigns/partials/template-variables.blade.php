{{--
    Template variables reference panel.
    Usage: @include('admin::commercial.campaigns.partials.template-variables')
--}}
<div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
        @lang('admin::app.commercial.campaigns.template.variables-title')
    </p>

    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
        @lang('admin::app.commercial.campaigns.template.variables-hint')
    </p>

    <div class="flex flex-col gap-1.5">
        @foreach (\Webkul\Commercial\Services\Template\CommercialCampaignTemplateRenderer::VARIABLES as $key => $desc)
            <div class="flex items-start justify-between gap-2">
                <button
                    type="button"
                    title="@lang('admin::app.commercial.campaigns.template.copy-hint')"
                    onclick="navigator.clipboard.writeText('{{ '{{' . $key . '}}' }}')"
                    class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700 hover:bg-blue-100 hover:text-blue-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-blue-900 dark:hover:text-blue-300"
                >
                    {{ '{{' . $key . '}}' }}
                </button>
                <span class="text-right text-xs text-gray-500 dark:text-gray-400">
                    @lang('admin::app.commercial.campaigns.template.var-' . str_replace(['.', '_'], '-', $key))
                </span>
            </div>
        @endforeach
    </div>
</div>
