<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NoteTemplateController;
use App\Http\Controllers\Api\RepairController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TechnicianController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\TechnicianOnly;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/content', [ContentController::class, 'index']);
    Route::get('/lookups', [LookupController::class, 'index']);
    Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
    Route::get('/specializations', [LookupController::class, 'specializations']);
    Route::get('/device-types', [LookupController::class, 'devices']);
    Route::get('/technicians', [TechnicianController::class, 'index']);
    Route::get('/technicians/{technician}', [TechnicianController::class, 'show'])->whereNumber('technician');
    Route::get('/technicians/{technician}/reviews', [TechnicianController::class, 'reviews'])->whereNumber('technician');
    Route::get('/technicians/{technician}/photo', [TechnicianController::class, 'photo'])->whereNumber('technician');
    Route::post('/technicians/{technician}/view', [SubscriptionController::class, 'view'])->whereNumber('technician')->middleware('throttle:30,1');
    Route::middleware('throttle:authentication')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgot']);
        Route::post('/reset-password', [AuthController::class, 'reset']);
    });
    // Even a suspended participant must be able to invalidate their session.
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::middleware(['auth:sanctum', ActiveAccount::class])->group(function () {
        Route::patch('/profile', [AuthController::class, 'profile']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/notifications', [DashboardController::class, 'notifications']);
        Route::patch('/notifications/{notification}/read', [DashboardController::class, 'read']);
        Route::prefix('technician')->middleware(TechnicianOnly::class)->group(function () {
            Route::get('/workshop', [WorkshopController::class, 'index']);
            Route::get('/workshop/repairs', [WorkshopController::class, 'repairs']);
            Route::get('/workshop/repairs/{repair}/documents/{kind}', [WorkshopController::class, 'document'])->whereNumber('repair')->whereIn('kind', ['quotation', 'job-sheet']);
            Route::apiResource('note-templates', NoteTemplateController::class)->parameters(['note-templates' => 'template'])->where(['template' => '[0-9]+'])->except('show');
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::post('/inventory', [InventoryController::class, 'store']);
            Route::patch('/inventory/{part}', [InventoryController::class, 'update'])->whereNumber('part');
            Route::post('/inventory/{part}/movements', [InventoryController::class, 'move'])->whereNumber('part');
            Route::get('/inventory-movements', [InventoryController::class, 'movements']);
            Route::get('/business-reports', [WorkshopController::class, 'report']);
            Route::get('/business-reports/export', [WorkshopController::class, 'export']);
            Route::get('/subscription', [SubscriptionController::class, 'index']);
            Route::post('/subscription/demo-payments', [SubscriptionController::class, 'store'])->middleware('throttle:10,1');
            Route::get('/subscription/analytics', [SubscriptionController::class, 'analytics']);
            Route::get('/profile', [TechnicianController::class, 'mine']);
            Route::get('/reviews', [TechnicianController::class, 'myReviews']);
            Route::patch('/profile', [TechnicianController::class, 'update']);
            Route::patch('/availability', [TechnicianController::class, 'availability']);
            Route::post('/photo', [TechnicianController::class, 'uploadPhoto']);
            Route::get('/photo', [TechnicianController::class, 'myPhoto']);
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/repair-requests', [RepairController::class, 'index']);
            Route::post('/repair-requests/{repair_request}/accept', [RepairController::class, 'accept']);
            Route::post('/repair-requests/{repair_request}/decline', [RepairController::class, 'decline']);
            Route::patch('/repair-requests/{repair_request}/status', [RepairController::class, 'transition']);
        });
        Route::apiResource('repair-requests', RepairController::class)->except('destroy');
        Route::post('/repair-requests/{repair_request}/cancel', [RepairController::class, 'cancel']);
        Route::get('/repair-requests/{repair_request}/history', [RepairController::class, 'history']);
        Route::patch('/repair-requests/{repair_request}/status', [RepairController::class, 'transition']);
        Route::post('/repair-requests/{repair_request}/estimates', [RepairController::class, 'estimate']);
        Route::post('/repair-requests/{repair_request}/approve-estimate', [RepairController::class, 'approve']);
        Route::post('/repair-requests/{repair_request}/attachments', [RepairController::class, 'attach']);
        Route::post('/repair-requests/{repair_request}/notes', [RepairController::class, 'note']);
        Route::get('/attachments/{attachment}', [RepairController::class, 'download']);
        Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'update']);
        Route::post('/reviews', [ReviewController::class, 'store']);
    });
    Route::get('/user', fn (Request $request) => new UserResource($request->user()))
        ->middleware(['auth:sanctum', ActiveAccount::class]);
});
