<?php

/**
 * Daftar folder yang ikut tersalin ke ponsel saat `native:run --watch` berjalan.
 *
 * `database` pernah hilang dari daftar ini, dan akibatnya tidak kelihatan sama
 * sekali: kode baru ikut tersalin sementara migrasi yang dibutuhkannya tidak,
 * jadi aplikasi berjalan di atas tabel yang kolomnya belum ada. Penulisan yang
 * gagal ditelan diam-diam oleh pelapor kerusakan, dan barulah ketahuan saat
 * laporan yang ditunggu tidak pernah datang.
 */
it('watches every folder the phone cannot run a change without', function (string $path) {
    expect(config('nativephp.hot_reload.watch_paths'))->toContain($path);
})->with(['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes']);

/**
 * Isinya memuat jalur absolut mesin pembangun, yang tidak ada di ponsel.
 */
it('never copies the build machine compiled config onto the phone', function () {
    expect(config('nativephp.hot_reload.exclude_patterns'))->toContain('bootstrap/cache');
});
