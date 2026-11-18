<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Ecwid\LegacyController;
use App\Http\Controllers\Ecwid\PaymentController;
use App\Http\Controllers\Ecwid\SettingsController;
use App\Http\Controllers\Ecwid\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response('Ecwid payment apps by S4D.', 200, ['Content-Type' => 'text/plain']));

/*
| Ecwid app endpoints, one set per registered Ecwid app ({app} = client_id).
| Register these in the Ecwid app settings:
|   paymentUrl   POST /ecwid/{client_id}/payment
|   iframeUrl    GET  /ecwid/{client_id}/settings
|   customJsUrl  GET  /ecwid/{client_id}/storefront.js
*/
Route::prefix('ecwid/{app}')->name('ecwid.')->group(function () {
    Route::post('payment', [PaymentController::class, 'request'])->name('payment');

    Route::get('settings', [SettingsController::class, 'show'])->name('settings');
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('settings', [SettingsController::class, 'save'])->name('settings.save');
        Route::post('settings/license', [SettingsController::class, 'checkLicense'])->name('settings.license');
        Route::post('settings/verify', [SettingsController::class, 'verifyGateway'])->name('settings.verify');
    });

    Route::get('storefront.js', [StorefrontController::class, 'script'])->name('storefront');
});

// Gateway return URLs ({transaction} = public ULID).
Route::get('pay/{transaction}/return', [PaymentController::class, 'return'])->name('payments.return');
Route::get('pay/{transaction}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

// v1 URLs already registered with Ecwid.
Route::post('payment-request.php', [LegacyController::class, 'paymentRequest']);
Route::get('index.html', [LegacyController::class, 'settings']);
Route::get('clientscript.js', [LegacyController::class, 'storefront']);
Route::post('stores/{store}/payment-request.php', [LegacyController::class, 'paymentRequest'])->where('store', '[A-Za-z0-9.\-]+');
Route::get('stores/{store}/index.php', [LegacyController::class, 'settings'])->where('store', '[A-Za-z0-9.\-]+');
Route::get('stores/{store}/', [LegacyController::class, 'settings'])->where('store', '[A-Za-z0-9.\-]+');

/*
| Admin
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::redirect('/', '/admin/logs')->name('home');

        Route::get('logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('logs/{log}', [LogController::class, 'show'])->name('logs.show');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

        Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/prune-logs', [AdminSettingsController::class, 'prune'])->name('settings.prune');
    });
});
