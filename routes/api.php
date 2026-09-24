<?php

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\DeviceReportController;
use App\Http\Controllers\Api\V1\InvoiceShareController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

/**
 * Pintu masuk untuk aplikasi ponsel. Buku selalu disebut lewat public_id, bukan id berurutan.
 *
 * Batas laju dihitung per pengguna, dan setiap rute hanya boleh punya satu batas:
 * dua throttle pada rute yang sama berbagi satu penghitung dan saling menghabiskan
 * jatah. Jalur yang ramai diberi longgar — satu sinkronisasi bisa mengunggah puluhan
 * foto struk berturut-turut — sedangkan yang mahal atau bisa disalahgunakan diperketat.
 */
Route::prefix('v1')->group(function () {
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('api.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('throttle:120,1')->group(function () {
            Route::post('logout', [LoginController::class, 'destroy'])->name('api.logout');

            Route::get('subscription', [SubscriptionController::class, 'show'])->name('api.subscription.show');

            Route::get('books', [BookController::class, 'index'])->name('api.books.index');

            Route::get('books/{book:public_id}/sync', [SyncController::class, 'show'])->name('api.sync.pull');
            Route::post('books/{book:public_id}/sync', [SyncController::class, 'store'])->name('api.sync.push');

            Route::get('books/{book:public_id}/transactions/{transactionPublicId}/receipt', [ReceiptController::class, 'show'])
                ->name('api.receipt.show');
            Route::post('books/{book:public_id}/transactions/{transactionPublicId}/receipt', [ReceiptController::class, 'store'])
                ->name('api.receipt.store');
        });

        Route::middleware('throttle:20,1')->group(function () {
            Route::post('device-reports', [DeviceReportController::class, 'store'])->name('api.device-reports.store');

            Route::post('books/{book:public_id}/invoices/{invoicePublicId}/share', [InvoiceShareController::class, 'store'])
                ->name('api.invoice.share');
        });

        Route::post('books', [BookController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('api.books.store');

        Route::post('subscription/payments', [SubscriptionController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('api.subscription.store');
    });
});
