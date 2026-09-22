<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Folder Sumber
    |--------------------------------------------------------------------------
    |
    | Folder berisi file unggahan pengguna yang perlu diarsipkan.
    |
    */

    'source' => storage_path('app/private'),

    /*
    |--------------------------------------------------------------------------
    | Folder yang Dilewati
    |--------------------------------------------------------------------------
    |
    | Sisa unggahan yang belum tersimpan dan hasil export tidak perlu ikut
    | diarsipkan: keduanya bisa dibuat ulang dari database kapan saja.
    |
    */

    'excluded_directories' => ['livewire-tmp', 'filament_exports'],

    /*
    |--------------------------------------------------------------------------
    | Folder Arsip
    |--------------------------------------------------------------------------
    |
    | Tujuan penyimpanan arsip file unggahan (foto struk & hasil export).
    | Di production folder ini harus berupa mount ke host, bukan volume yang
    | sama dengan file aslinya, agar arsipnya selamat saat volume asli hilang.
    |
    */

    'destination' => env('BACKUP_FILES_PATH') ?: storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Jumlah Arsip yang Disimpan
    |--------------------------------------------------------------------------
    |
    | Arsip paling lama dihapus setelah jumlah ini terlampaui.
    |
    */

    'keep' => (int) (env('BACKUP_FILES_KEEP') ?: 14),

];
