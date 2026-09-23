<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kontak Publik
    |--------------------------------------------------------------------------
    |
    | Ditampilkan di halaman depan untuk orang yang belum punya akun, karena
    | tiket bantuan di dalam aplikasi hanya bisa dibuka setelah login. Nilai
    | cadangan saja: yang dipakai adalah pengaturan yang disimpan admin.
    |
    */

    'whatsapp' => env('CONTACT_WHATSAPP', ''),

    'email' => env('CONTACT_EMAIL', ''),

];
