<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Configuration\ConfigurationController;

Route::controller(ConfigurationController::class)->prefix('configuration')->group(function () {
    Route::get('search', 'search')->name('admin.configuration.search');

    Route::post('smtp/test', 'testSmtp')->name('admin.configuration.smtp.test');
    Route::post('whatsapp/test', 'testWhatsApp')->name('admin.configuration.whatsapp.test');
    Route::post('whatsapp/test-message', 'testWhatsAppMessage')->name('admin.configuration.whatsapp.test_message');

    Route::prefix('{slug?}/{slug2?}')->group(function () {
        Route::get('', 'index')->name('admin.configuration.index');

        Route::post('', 'store')->name('admin.configuration.store');

        Route::get('{path}', 'download')->name('admin.configuration.download');
    });
});
