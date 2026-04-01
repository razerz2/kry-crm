<?php

namespace Webkul\Admin\Http\Controllers\Commercial;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignAuditDeliveryDataGrid;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignDataGrid;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignDeliveryDataGrid;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignRunDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Models\CommercialCampaignRun;
use Webkul\Commercial\Models\CrmProduct;
use Webkul\Commercial\Services\CampaignScheduleService;
use Webkul\Commercial\Services\CommercialCampaignDeliveryService;
use Webkul\Commercial\Services\CommercialCampaignMetricsService;
use Webkul\Commercial\Services\CommercialCampaignService;
use Webkul\Commercial\Services\CommercialCampaignStateGuard;
use Webkul\Commercial\Services\Template\CommercialCampaignTemplateRenderer;
use Webkul\Commercial\Services\Template\TemplateRenderContext;

class CommercialCampaignController extends Controller
{
    public function __construct(
        protected CommercialCampaignService $campaignService,
        protected CommercialCampaignDeliveryService $deliveryService,
        protected CommercialCampaignMetricsService $metricsService,
        protected CampaignScheduleService $scheduleService,
        protected CommercialCampaignTemplateRenderer $renderer,
    ) {}

    /**
     * Display the campaign listing.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(CommercialCampaignDataGrid::class)->process();
        }

        return view('admin::commercial.campaigns.index');
    }

    /**
     * Display commercial campaign runs (executions).
     */
    public function executions(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return datagrid(CommercialCampaignRunDataGrid::class)->process();
        }

        $campaign = null;

        if ($campaignId = (int) $request->query('campaign_id')) {
            $campaign = CommercialCampaign::find($campaignId);
        }

        return view('admin::commercial.executions.index', compact('campaign'));
    }

    /**
     * Show execution details.
     */
    public function showExecution(int $id): View
    {
        $run = CommercialCampaignRun::with(['campaign', 'creator'])->findOrFail($id);

        return view('admin::commercial.executions.show', compact('run'));
    }

    /**
     * Open delivery audit scoped to execution.
     */
    public function executionDeliveries(int $id): RedirectResponse
    {
        $run = CommercialCampaignRun::findOrFail($id);

        return redirect()->route('admin.commercial.deliveries.index', [
            'campaign_id' => $run->commercial_campaign_id,
            'run_id' => $run->id,
        ]);
    }

    /**
     * Display delivery audit across campaigns/runs.
     */
    public function deliveryAudit(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return datagrid(CommercialCampaignAuditDeliveryDataGrid::class)->process();
        }

        $campaign = null;
        $run = null;

        if ($campaignId = (int) $request->query('campaign_id')) {
            $campaign = CommercialCampaign::find($campaignId);
        }

        if ($runId = (int) $request->query('run_id')) {
            $run = CommercialCampaignRun::with('campaign')->find($runId);
        }

        return view('admin::commercial.deliveries.index', compact('campaign', 'run'));
    }

    /**
     * Show detail page for a delivery from global audit.
     */
    public function showAuditDelivery(int $id): View
    {
        $delivery = CommercialCampaignDelivery::with(['campaign', 'run', 'logs'])->findOrFail($id);

        return view('admin::commercial.deliveries.show', compact('delivery'));
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(): View
    {
        $products = CrmProduct::where('is_active', true)->orderBy('name')->get();

        return view('admin::commercial.campaigns.create', compact('products'));
    }

    /**
     * Store a newly created campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'channel' => 'required|in:email,whatsapp,both',
            'subject' => 'nullable|string|max:255',
            'message_body' => 'nullable|string',
            'execution_type' => 'nullable|in:manual,once,recurring,windowed_recurring',
            'timezone' => 'nullable|string|max:64',
            'run_at' => 'nullable|date',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,interval',
            'interval_value' => 'nullable|integer|min:1|max:100000',
            'interval_unit' => 'nullable|in:minutes,hours,days',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'time_of_day' => 'nullable|date_format:H:i',
            'window_start_time' => 'nullable|date_format:H:i',
            'window_end_time' => 'nullable|date_format:H:i',
            'max_runs' => 'nullable|integer|min:1|max:100000',
        ]);

        $validated['filters'] = $this->extractFilters($request);
        $validated = array_merge($validated, $this->extractSchedule($request));

        $campaign = $this->campaignService->create($validated);

        session()->flash('success', trans('admin::app.commercial.campaigns.create-success'));

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Display the specified campaign.
     */
    public function show(int $id): View
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $audience = $this->campaignService->getAudience($campaign);
        $recentRuns = CommercialCampaignRun::where('commercial_campaign_id', $campaign->id)
            ->latest('id')
            ->limit(20)
            ->get();
        $products = CrmProduct::where('is_active', true)->orderBy('name')->get();
        $stats = $this->deliveryService->getDeliveryStats($campaign);

        $metrics = null;
        if ($campaign->total_deliveries > 0) {
            $metrics = [
                'status' => $stats,
                'channels' => $this->metricsService->channelBreakdown($campaign->id),
                'providers' => $this->metricsService->providerBreakdown($campaign->id),
                'errors' => $this->metricsService->recentErrors($campaign->id),
            ];
        }

        return view('admin::commercial.campaigns.show', compact('campaign', 'audience', 'products', 'stats', 'metrics', 'recentRuns'));
    }

    /**
     * Show the form for editing the specified campaign.
     */
    public function edit(int $id): View
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $audience = $this->campaignService->getAudience($campaign, 50);
        $recentRuns = CommercialCampaignRun::where('commercial_campaign_id', $campaign->id)
            ->latest('id')
            ->limit(10)
            ->get();
        $products = CrmProduct::where('is_active', true)->orderBy('name')->get();
        $stats = $this->deliveryService->getDeliveryStats($campaign);
        $readinessIssues = $this->campaignService->readinessIssues($campaign);
        $audienceStale = $this->campaignService->isAudienceStale($campaign);

        return view('admin::commercial.campaigns.edit', compact(
            'campaign', 'audience', 'products', 'stats', 'readinessIssues', 'audienceStale', 'recentRuns'
        ));
    }

    /**
     * Update the specified campaign.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'channel' => 'required|in:email,whatsapp,both',
            'status' => 'nullable|in:draft,ready,scheduled,running,paused,completed,canceled,archived,sent,failed,partially_sent',
            'subject' => 'nullable|string|max:255',
            'message_body' => 'nullable|string',
            'execution_type' => 'nullable|in:manual,once,recurring,windowed_recurring',
            'timezone' => 'nullable|string|max:64',
            'run_at' => 'nullable|date',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'recurrence_type' => 'nullable|in:daily,weekly,monthly,interval',
            'interval_value' => 'nullable|integer|min:1|max:100000',
            'interval_unit' => 'nullable|in:minutes,hours,days',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'time_of_day' => 'nullable|date_format:H:i',
            'window_start_time' => 'nullable|date_format:H:i',
            'window_end_time' => 'nullable|date_format:H:i',
            'max_runs' => 'nullable|integer|min:1|max:100000',
        ]);

        $validated['filters'] = $this->extractFilters($request);
        $validated = array_merge($validated, $this->extractSchedule($request, $campaign));

        try {
            $updated = $this->campaignService->update($campaign, $validated);

            // Notify if the update silently invalidated the audience
            if (! $updated->hasAudience() && $campaign->hasAudience()) {
                session()->flash('warning', trans('admin::app.commercial.campaigns.audience-invalidated'));
            } else {
                session()->flash('success', trans('admin::app.commercial.campaigns.update-success'));
            }
        } catch (\RuntimeException $e) {
            session()->flash('error', $this->translateGuardException($e));

            return redirect()->route('admin.commercial.campaigns.show', $campaign->id);
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(int $id): JsonResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            app(CommercialCampaignStateGuard::class)->assertDeletable($campaign);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $this->translateGuardException($e),
            ], 422);
        }

        $campaign->audienceMembers()->delete();
        $campaign->delete();

        return response()->json([
            'message' => trans('admin::app.commercial.campaigns.destroy-success'),
        ]);
    }

    /**
     * Mass destroy campaigns.
     */
    public function massDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('indices', []);

        foreach ($ids as $id) {
            $campaign = CommercialCampaign::find($id);

            if ($campaign) {
                $campaign->audienceMembers()->delete();
                $campaign->delete();
            }
        }

        return response()->json([
            'message' => trans('admin::app.commercial.campaigns.destroy-success'),
        ]);
    }

    /**
     * Generate audience preview (AJAX).
     */
    public function previewAudience(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $limit = (int) $request->input('preview_limit', 20);

        $result = $this->campaignService->generatePreview($filters, $limit);

        return response()->json([
            'stats' => $result['stats'],
            'items' => $result['items']->map(fn ($item) => $item->toArray())->values(),
        ]);
    }

    /**
     * Freeze (persist) audience for a campaign.
     */
    public function freezeAudience(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        if ($campaign->isLocked()) {
            session()->flash('error', trans('admin::app.commercial.campaigns.locked-error'));

            return redirect()->route('admin.commercial.campaigns.show', $campaign->id);
        }

        $this->campaignService->freezeAudience($campaign);

        session()->flash('success', trans('admin::app.commercial.campaigns.audience-frozen'));

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Recalculate audience for a campaign.
     */
    public function recalculateAudience(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        if ($campaign->isLocked()) {
            session()->flash('error', trans('admin::app.commercial.campaigns.locked-error'));

            return redirect()->route('admin.commercial.campaigns.show', $campaign->id);
        }

        $this->campaignService->recalculateAudience($campaign);

        session()->flash('success', trans('admin::app.commercial.campaigns.audience-recalculated'));

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Mark campaign as ready.
     */
    public function markReady(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->campaignService->markReady($campaign);
            session()->flash('success', trans('admin::app.commercial.campaigns.mark-ready-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $this->translateGuardException($e));
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Revert campaign to draft.
     */
    public function markDraft(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->campaignService->markDraft($campaign);
            session()->flash('success', trans('admin::app.commercial.campaigns.mark-draft-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $this->translateGuardException($e));
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Schedule the campaign for automatic execution.
     */
    public function schedule(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->scheduleService->scheduleCampaign($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.schedule.schedule-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Pause automatic executions.
     */
    public function pause(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->scheduleService->pauseCampaign($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.schedule.pause-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Resume automatic executions.
     */
    public function resume(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->scheduleService->resumeCampaign($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.schedule.resume-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Cancel campaign and stop new executions.
     */
    public function cancel(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->scheduleService->cancelCampaign($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.schedule.cancel-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Queue an immediate execution run.
     */
    public function runNow(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            app(CommercialCampaignStateGuard::class)->assertDispatchable($campaign);
            $this->scheduleService->queueImmediateExecution($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.schedule.run-now-success'));
        } catch (\RuntimeException $e) {
            $message = str_starts_with($e->getMessage(), 'campaign.')
                ? $this->translateGuardException($e)
                : $e->getMessage();

            session()->flash('error', $message);
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Recalculate next_run_at based on current scheduling settings.
     */
    public function recalculateNextRun(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        $this->scheduleService->recalculateNextRun($campaign, auth()->id());

        session()->flash('success', trans('admin::app.commercial.campaigns.schedule.recalculate-next-success'));

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Dispatch the campaign (start sending).
     */
    public function dispatchCampaign(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            app(CommercialCampaignStateGuard::class)->assertDispatchable($campaign);
            $this->scheduleService->queueImmediateExecution($campaign, auth()->id());
            session()->flash('success', trans('admin::app.commercial.campaigns.dispatch-success'));
        } catch (\RuntimeException $e) {
            $message = str_starts_with($e->getMessage(), 'campaign.')
                ? $this->translateGuardException($e)
                : $e->getMessage();

            session()->flash('error', $message);
        }

        return redirect()->route('admin.commercial.campaigns.edit', $campaign->id);
    }

    /**
     * Delivery listing page + DataGrid JSON for a campaign.
     */
    public function deliveries(Request $request, int $id): View|JsonResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        if ($request->ajax()) {
            return datagrid(CommercialCampaignDeliveryDataGrid::class)->process();
        }

        $stats = $this->deliveryService->getDeliveryStats($campaign);

        return view('admin::commercial.campaigns.deliveries', compact('campaign', 'stats'));
    }

    /**
     * Show the detail page for a single delivery.
     */
    public function showDelivery(int $id, int $deliveryId): View
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $delivery = CommercialCampaignDelivery::with('logs')
            ->where('commercial_campaign_id', $id)
            ->findOrFail($deliveryId);

        return view('admin::commercial.campaigns.delivery-show', compact('campaign', 'delivery'));
    }

    /**
     * Manually trigger a metrics refresh for a campaign.
     */
    public function recalculateMetrics(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $this->metricsService->refresh($campaign);

        session()->flash('success', trans('admin::app.commercial.campaigns.metrics.recalculated'));

        return redirect()->route('admin.commercial.campaigns.deliveries', $campaign->id);
    }

    /**
     * Render a template preview for a campaign (AJAX).
     *
     * Uses the first frozen audience member as sample context, or dummy data
     * if no audience has been frozen yet.
     */
    public function previewTemplate(Request $request, int $id): JsonResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        $subject = $request->input('subject', '');
        $body = $request->input('message_body', '');

        $firstMember = $campaign->audienceMembers()->first();

        $ctx = $firstMember
            ? TemplateRenderContext::fromAudienceMember(
                $firstMember,
                $campaign,
                $campaign->channel === 'both' ? 'email' : $campaign->channel
            )
            : TemplateRenderContext::dummy($campaign);

        return response()->json([
            'subject' => $this->renderer->renderSubject($subject, $ctx),
            'body' => $this->renderer->renderBody($body, $ctx),
            'sample' => $ctx->toVars(),
            'is_dummy' => $firstMember === null,
        ]);
    }

    /**
     * Duplicate a campaign (copy metadata, filters, subject, body).
     * Audience and deliveries are NOT copied.
     */
    public function duplicate(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $copy = $this->campaignService->duplicate($campaign);

        session()->flash('success', trans('admin::app.commercial.campaigns.duplicate.success'));

        return redirect()->route('admin.commercial.campaigns.edit', $copy->id);
    }

    /**
     * Translate a StateGuard RuntimeException key into a human-readable string.
     *
     * Guard throws keys in the format "campaign.something:context" or
     * "campaign.something.issues:key1|key2|key3".
     * Fall back to the raw message if no translation exists.
     */
    protected function translateGuardException(\RuntimeException $e): string
    {
        $raw = $e->getMessage();

        // Structured readiness issues: "campaign.mark-ready.issues:k1|k2|k3"
        if (str_starts_with($raw, 'campaign.mark-ready.issues:')) {
            $issueKeys = explode('|', substr($raw, strlen('campaign.mark-ready.issues:')));
            $lines = array_map(
                fn ($k) => trans('admin::app.commercial.campaigns.guard.'.str_replace(['.', ':'], '-', $k)),
                $issueKeys
            );

            return implode(' ', $lines);
        }

        // Simple keyed message: "campaign.edit-blocked:sending"
        if (str_contains($raw, ':')) {
            [$key, $ctx] = explode(':', $raw, 2);
            $transKey = 'admin::app.commercial.campaigns.guard.'.str_replace('.', '-', $key);

            return trans($transKey, ['status' => trans('admin::app.commercial.campaigns.statuses.'.$ctx)]);
        }

        // Plain key without context
        $transKey = 'admin::app.commercial.campaigns.guard.'.str_replace(['.', ':'], '-', $raw);
        $translated = trans($transKey);

        return $translated !== $transKey ? $translated : $raw;
    }

    /**
     * Extract and normalize schedule values from request.
     */
    protected function extractSchedule(Request $request, ?CommercialCampaign $campaign = null): array
    {
        return $this->scheduleService->prepareCampaignData([
            'execution_type' => $request->input('execution_type'),
            'timezone' => $request->input('timezone'),
            'run_at' => $request->input('run_at'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'recurrence_type' => $request->input('recurrence_type'),
            'interval_value' => $request->input('interval_value'),
            'interval_unit' => $request->input('interval_unit'),
            'days_of_week' => $request->input('days_of_week', []),
            'day_of_month' => $request->input('day_of_month'),
            'time_of_day' => $request->input('time_of_day'),
            'window_start_time' => $request->input('window_start_time'),
            'window_end_time' => $request->input('window_end_time'),
            'max_runs' => $request->input('max_runs'),
        ], $campaign);
    }

    /**
     * Extract filter values from request.
     */
    protected function extractFilters(Request $request): array
    {
        return [
            'entity_type' => $request->input('filter_entity_type', 'both'),
            'crm_product_ids' => array_filter(array_map('intval', $request->input('filter_crm_product_ids', []))),
            'commercial_statuses' => $request->input('filter_commercial_statuses', []),
            'segment' => $request->input('filter_segment') ?: null,
            'channel' => $request->input('filter_channel') ?: null,
            'only_with_email' => (bool) $request->input('filter_only_with_email', false),
            'only_with_phone' => (bool) $request->input('filter_only_with_phone', false),
            'only_primary_contact_if_organization' => (bool) $request->input('filter_only_primary_contact', false),
            'include_inactive_customer' => (bool) $request->input('filter_include_inactive_customer', true),
            'include_former_customer' => (bool) $request->input('filter_include_former_customer', true),
            'search' => $request->input('filter_search') ?: null,
        ];
    }
}
