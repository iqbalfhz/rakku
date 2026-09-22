<?php

use App\Http\Controllers\SharedInvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::get('invoices/{invoice}/pdf', SharedInvoicePdfController::class)
    ->middleware('signed')
    ->name('invoices.shared-pdf');
