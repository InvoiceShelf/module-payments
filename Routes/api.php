<?php

use Modules\Payments\Http\Controllers\ActivePaymentProvidersController;
use Modules\Payments\Http\Controllers\PaymentController;
use Modules\Payments\Http\Controllers\PaymentDriversController;
use Modules\Payments\Http\Controllers\PaymentProvidersController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Admin Routes
// ----------------------------------

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::get('payment-drivers', PaymentDriversController::class);

    Route::apiResource('payment-providers', PaymentProvidersController::class);
});

// Payment Routes
// ----------------------------------

Route::get('/{company:slug}/active-payment-providers', ActivePaymentProvidersController::class);

Route::post('/{company:slug}/generate-payment/{invoice}', [PaymentController::class, 'generatePayment']);

Route::post('/{company:slug}/confirm-transaction/{transaction_id}', [PaymentController::class, 'confirmTransaction']);
