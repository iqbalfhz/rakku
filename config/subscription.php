<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rekening Tujuan Transfer
    |--------------------------------------------------------------------------
    |
    | Nilai cadangan saja: yang dipakai adalah pengaturan yang disimpan admin
    | lewat halaman Pengaturan Langganan di panel /admin.
    |
    */

    'bank' => [
        'name' => env('SUBSCRIPTION_BANK_NAME', 'BCA'),
        'account_number' => env('SUBSCRIPTION_BANK_ACCOUNT_NUMBER', '0000000000'),
        'account_holder' => env('SUBSCRIPTION_BANK_ACCOUNT_HOLDER', 'Nama Pemilik Rekening'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Harga Paket
    |--------------------------------------------------------------------------
    |
    | Kunci mengikuti nilai enum SubscriptionPackage. Harga yang berlaku
    | disalin ke setiap pengajuan pembayaran, jadi perubahan di sini tidak
    | mengubah tagihan yang sudah diajukan.
    |
    */

    'prices' => [
        'monthly' => 25_000,
        'quarterly' => 65_000,
        'yearly' => 250_000,
    ],

];
