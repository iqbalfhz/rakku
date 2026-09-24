<?php

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\InvoiceShareController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\ReceiptController;
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

        Route::get('books', [BookController::class, 'index'])->name('api.books.index');
        Route::post('books', [BookController::class, 'store'])->name('api.books.store');

        Route::get('books/{book:public_id}/sync', [SyncController::class, 'show'])->name('api.sync.pull');
        Route::post('books/{book:public_id}/sync', [SyncController::class, 'store'])->name('api.sync.push');

        Route::get('books/{book:public_id}/transactions/{transactionPublicId}/receipt', [ReceiptController::class, 'show'])
            ->name('api.receipt.show');
        Route::post('books/{book:public_id}/transactions/{transactionPublicId}/receipt', [ReceiptController::class, 'store'])
            ->name('api.receipt.store');

        Route::post('books/{book:public_id}/invoices/{invoicePublicId}/share', [InvoiceShareController::class, 'store'])
            ->name('api.invoice.share');
    });
});
