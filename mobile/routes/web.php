<?php

use App\Http\Middleware\RedirectIfSignedIn;
use App\Http\Middleware\RequireSignedIn;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.login')
    ->middleware(RedirectIfSignedIn::class)
    ->name('login');

Route::middleware(RequireSignedIn::class)->group(function () {
    Route::view('/beranda', 'pages.home')->name('home');
    Route::view('/riwayat', 'pages.history')->name('history');
    Route::view('/buku', 'pages.books')->name('books');

    Route::get('/catat/{publicId?}', fn (?string $publicId = null) => view('pages.record', ['publicId' => $publicId]))
        ->name('record');

    Route::view('/pengaturan', 'pages.setup')->name('setup');
    Route::view('/langganan', 'pages.subscription')->name('subscription');
    Route::view('/laporan', 'pages.report')->name('report');
    Route::view('/anggaran', 'pages.budgets')->name('budgets');
    Route::view('/berulang', 'pages.recurring')->name('recurring');

    Route::view('/invoice', 'pages.invoices')->name('invoices');
    Route::view('/invoice/baru', 'pages.invoice')->name('invoice.create');
    Route::get('/invoice/{publicId}', fn (string $publicId) => view('pages.invoice', ['publicId' => $publicId]))
        ->name('invoice.show');

    Route::view('/utang', 'pages.debts')->name('debts');
    Route::view('/utang/baru', 'pages.debt')->name('debt.create');
    Route::get('/utang/{publicId}', fn (string $publicId) => view('pages.debt', ['publicId' => $publicId]))
        ->name('debt.show');
});
