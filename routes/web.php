<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\SharedInvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('invoices/{invoice}/pdf', SharedInvoicePdfController::class)
    ->middleware('signed')
    ->name('invoices.shared-pdf');
