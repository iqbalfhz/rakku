<?php

use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/**
 * Pintu masuk untuk aplikasi ponsel. Buku selalu disebut lewat public_id, bukan id berurutan.
 */
Route::prefix('v1')->group(function () {
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('api.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('api.logout');

        Route::get('books/{book:public_id}/sync', [SyncController::class, 'show'])->name('api.sync.pull');
        Route::post('books/{book:public_id}/sync', [SyncController::class, 'store'])->name('api.sync.push');
    });
});
