<?php

namespace Webkul\Admin\Http\Controllers\Commercial;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignDataGrid;
use Webkul\Admin\DataGrids\Commercial\CommercialCampaignDeliveryDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Commercial\Models\CommercialCampaign;
use Webkul\Commercial\Models\CommercialCampaignDelivery;
use Webkul\Commercial\Models\CrmProduct;
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
        ]);

        $validated['filters'] = $this->extractFilters($request);

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

        return view('admin::commercial.campaigns.show', compact('campaign', 'audience', 'products', 'stats', 'metrics'));
    }

    /**
     * Show the form for editing the specified campaign.
     */
    public function edit(int $id): View
    {
        $campaign = CommercialCampaign::findOrFail($id);
        $audience = $this->campaignService->getAudience($campaign, 50);
        $products = CrmProduct::where('is_active', true)->orderBy('name')->get();
        $stats = $this->deliveryService->getDeliveryStats($campaign);
        $readinessIssues = $this->campaignService->readinessIssues($campaign);
        $audienceStale = $this->campaignService->isAudienceStale($campaign);

        return view('admin::commercial.campaigns.edit', compact(
            'campaign', 'audience', 'products', 'stats', 'readinessIssues', 'audienceStale'
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
            'status' => 'nullable|in:draft,ready,archived',
            'subject' => 'nullable|string|max:255',
            'message_body' => 'nullable|string',
        ]);

        $validated['filters'] = $this->extractFilters($request);

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
     * Dispatch the campaign (start sending).
     */
    public function dispatch(int $id): RedirectResponse
    {
        $campaign = CommercialCampaign::findOrFail($id);

        try {
            $this->deliveryService->dispatch($campaign);
            session()->flash('success', trans('admin::app.commercial.campaigns.dispatch-success'));
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('admin.commercial.campaigns.deliveries', $campaign->id);
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
