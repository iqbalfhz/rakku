<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\SharedInvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Untuk layanan pemantauan di luar server. Dibatasi karena terbuka tanpa login,
// dan sebuah pemantau yang waras tidak mengetuk lebih sering dari ini.
Route::get('sehat', HealthController::class)->middleware('throttle:30,1')->name('health');

Route::get('kebijakan-privasi', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('syarat-layanan', [LegalController::class, 'terms'])->name('legal.terms');

Route::get('invoices/{invoice}/pdf', SharedInvoicePdfController::class)
    ->middleware('signed')
    ->name('invoices.shared-pdf');
