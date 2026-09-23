<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alamat Server
    |--------------------------------------------------------------------------
    |
    | Server RakKu yang disinkroni aplikasi ini. Saat mencoba di emulator dengan
    | server lokal, alamatnya bukan localhost melainkan http://10.0.2.2:8000,
    | karena emulator Android melihat komputer Anda lewat alamat itu.
    |
    */

    'server_url' => env('RAKKU_SERVER_URL', 'https://rakku.iqbalfhz.my.id'),

];
