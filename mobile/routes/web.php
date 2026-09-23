<?php

use App\Http\Middleware\RequireSignedIn;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.login')->name('login');

Route::view('/beranda', 'pages.home')
    ->middleware(RequireSignedIn::class)
    ->name('home');
