<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Commercial\CommercialCampaignController;

Route::prefix('commercial')->group(function () {
    /**
     * Commercial Campaign routes.
     */
    Route::controller(CommercialCampaignController::class)->prefix('campaigns')->group(function () {
        Route::get('', 'index')->name('admin.commercial.campaigns.index');

        Route::get('create', 'create')->name('admin.commercial.campaigns.create');

        Route::post('create', 'store')->name('admin.commercial.campaigns.store');

        Route::get('view/{id}', 'show')->name('admin.commercial.campaigns.show');

        Route::get('edit/{id}', 'edit')->name('admin.commercial.campaigns.edit');

        Route::put('edit/{id}', 'update')->name('admin.commercial.campaigns.update');

        Route::delete('{id}', 'destroy')->name('admin.commercial.campaigns.destroy');

        Route::post('mass-destroy', 'massDestroy')->name('admin.commercial.campaigns.mass_destroy');

        Route::post('preview-audience', 'previewAudience')->name('admin.commercial.campaigns.preview_audience');

        Route::post('{id}/preview-template', 'previewTemplate')->name('admin.commercial.campaigns.preview_template');

        Route::post('{id}/freeze-audience', 'freezeAudience')->name('admin.commercial.campaigns.freeze_audience');

        Route::post('{id}/recalculate-audience', 'recalculateAudience')->name('admin.commercial.campaigns.recalculate_audience');

        Route::post('{id}/mark-ready', 'markReady')->name('admin.commercial.campaigns.mark_ready');

        Route::post('{id}/duplicate', 'duplicate')->name('admin.commercial.campaigns.duplicate');

        Route::post('{id}/mark-draft', 'markDraft')->name('admin.commercial.campaigns.mark_draft');

        Route::post('{id}/dispatch', 'dispatchCampaign')->name('admin.commercial.campaigns.dispatch');
        Route::post('{id}/run-now', 'runNow')->name('admin.commercial.campaigns.run_now');
        Route::post('{id}/schedule', 'schedule')->name('admin.commercial.campaigns.schedule');
        Route::post('{id}/pause', 'pause')->name('admin.commercial.campaigns.pause');
        Route::post('{id}/resume', 'resume')->name('admin.commercial.campaigns.resume');
        Route::post('{id}/cancel', 'cancel')->name('admin.commercial.campaigns.cancel');
        Route::post('{id}/recalculate-next-run', 'recalculateNextRun')->name('admin.commercial.campaigns.recalculate_next_run');

        Route::get('{id}/deliveries', 'deliveries')->name('admin.commercial.campaigns.deliveries');

        Route::get('{id}/deliveries/{deliveryId}', 'showDelivery')->name('admin.commercial.campaigns.delivery_show');

        Route::post('{id}/recalculate-metrics', 'recalculateMetrics')->name('admin.commercial.campaigns.recalculate_metrics');
    });

    /**
     * Commercial campaign run routes (executions).
     */
    Route::controller(CommercialCampaignController::class)->prefix('executions')->group(function () {
        Route::get('', 'executions')->name('admin.commercial.executions.index');

        Route::get('view/{id}', 'showExecution')->name('admin.commercial.executions.show');

        Route::get('{id}/deliveries', 'executionDeliveries')->name('admin.commercial.executions.deliveries');
    });

    /**
     * Commercial delivery audit routes.
     */
    Route::controller(CommercialCampaignController::class)->prefix('deliveries')->group(function () {
        Route::get('', 'deliveryAudit')->name('admin.commercial.deliveries.index');

        Route::get('view/{id}', 'showAuditDelivery')->name('admin.commercial.deliveries.show');
    });
});
