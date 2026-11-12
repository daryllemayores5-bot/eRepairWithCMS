<?php

use App\Http\Controllers\Cp\OperationsController;
use App\Http\Controllers\Cp\SubscriptionController;
use App\Http\Middleware\AdministratorOnly;
use Illuminate\Support\Facades\Route;

Route::middleware(AdministratorOnly::class)->prefix('erepair')->name('erepair.')->group(function () {
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions');
    Route::post('/subscriptions/payments/{payment}', [SubscriptionController::class, 'decide'])->name('subscriptions.decide');
    Route::post('/subscriptions/plans/{plan}', [SubscriptionController::class, 'plan'])->name('subscriptions.plan');
    Route::get('/revenue', [SubscriptionController::class, 'revenue'])->name('revenue');
    Route::get('/', [OperationsController::class, 'index'])->name('index');
    Route::get('/technicians/{technician}/photo', [OperationsController::class, 'photo'])->name('photo');
    Route::post('/technicians/{technician}/verify', [OperationsController::class, 'verify'])->name('verify');
    Route::post('/technicians/{technician}/location', [OperationsController::class, 'location'])->name('location');
    Route::post('/users/{user}', [OperationsController::class, 'user'])->name('user');
    Route::post('/reviews/{review}', [OperationsController::class, 'review'])->name('review');
    Route::post('/catalog/{kind}', [OperationsController::class, 'catalog'])->name('catalog');
    Route::get('/reports.csv', [OperationsController::class, 'report'])->name('report');
});
