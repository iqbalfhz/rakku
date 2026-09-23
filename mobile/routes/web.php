<?php

use App\Http\Middleware\RequireSignedIn;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.login')->name('login');

Route::middleware(RequireSignedIn::class)->group(function () {
    Route::view('/beranda', 'pages.home')->name('home');
    Route::view('/catat', 'pages.record')->name('record');
    Route::view('/riwayat', 'pages.history')->name('history');
});
