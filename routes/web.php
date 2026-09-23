<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\SharedInvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('kebijakan-privasi', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('syarat-layanan', [LegalController::class, 'terms'])->name('legal.terms');

Route::get('invoices/{invoice}/pdf', SharedInvoicePdfController::class)
    ->middleware('signed')
    ->name('invoices.shared-pdf');
