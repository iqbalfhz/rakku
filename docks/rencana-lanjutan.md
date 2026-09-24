# RakKu — Rencana Lanjutan

Daftar pekerjaan yang tersisa menuju rilis publik, disusun pada **24 September 2026** setelah seluruh fitur premium selesai dibawa ke aplikasi ponsel.

Urutannya bukan urutan besar-kecil, melainkan urutan **mahal-murahnya diperbaiki belakangan**. Yang sudah terlanjur ada di ponsel orang lain jauh lebih mahal diperbaiki daripada yang masih di laptop.

---

## A. Menghalangi masuk Play Store

| # | Pekerjaan | Kenapa mendesak |
|---|---|---|
| 1 | ~~**Hapus akun dari dalam aplikasi**~~ — **selesai 24 Sep 2026** | Google **mewajibkan** aplikasi dengan pendaftaran akun menyediakan penghapusan akun dari dalam aplikasi *dan* lewat tautan web. Sekarang [`DeleteAccountAction`](../app/Filament/App/Actions/DeleteAccountAction.php) hanya ada di panel web. Tanpa ini, review ditolak. |
| 2 | **Ikon dan splash screen** | `mobile/resources/` baru berisi css, js, views. Aplikasi masih memakai ikon bawaan NativePHP. |
| 3 | **Keystore & build rilis bertanda tangan** | `NATIVEPHP_APP_VERSION` masih `DEBUG` dan `version_code` masih `1`. Keduanya harus naik tiap rilis. |
| 4 | **Data safety form** | Harus jujur menyebut aplikasi mengirim email, catatan keuangan, foto struk, dan laporan kerusakan ke server sendiri. |

---

## B. Lubang yang tersisa di aplikasi ponsel

| # | Pekerjaan | Catatan |
|---|---|---|
| 5 | ~~**Transfer antar akun**~~ — **selesai 24 Sep 2026** | Server punya [`Transfer`](../app/Models/Transfer.php), ponsel tidak. Terlewat waktu memetakan fitur premium — transfer memang bukan premium. Tanpa ini, memindahkan uang laci ke rekening harus ditulis sebagai dua transaksi terpisah, dan laporan jadi salah karena keduanya terhitung pemasukan dan pengeluaran sungguhan. |
| 6 | **Pendaftaran akun** | Satu-satunya kalimat tersisa di aplikasi yang menyuruh membuka browser. |
| 7 | **Tiket dukungan** | Semula diputuskan tetap di web. Keputusan itu ditarik: pengguna yang aplikasinya bermasalah justru saat itulah paling butuh mengadu. |
| 8 | **Cari dan saring di riwayat** | Setelah ratusan catatan, layar riwayat jadi gulungan panjang tanpa alat bantu. |

---

## C. Ketahanan sebelum dipakai orang lain

| # | Pekerjaan | Catatan |
|---|---|---|
| 9 | **Mata untuk server** | Ponsel sudah mengabarkan kerusakannya lewat [`DiagnosticsReporter`](../mobile/app/Services/DiagnosticsReporter.php); server masih diam — hanya `laravel.log` yang tidak ada yang membaca. |
| 10 | **Backup off-site** | Cadangan masih di mesin yang sama dengan datanya. Perlu persetujuan menambah `league/flysystem-aws-s3-v3`. |
| 11 | **Uptime monitoring** | Kalau server mati jam 2 pagi, tidak ada yang tahu sampai ada yang mengeluh. |
| 12 | **Masa berlaku token Sanctum** | Token tidak pernah kedaluwarsa. Ponsel hilang berarti akses selamanya, tanpa cara mencabut selain menghapus baris di database. |
| — | **Pola backfill `public_id`** | Migrasi mengisi `public_id` satu baris per satu `UPDATE`. Aman untuk data sekarang, terlalu lambat kalau tabel sudah besar. Ganti ke batch sebelum jumlah pengguna naik. |

---

## D. Fitur baru

| # | Pekerjaan | Catatan |
|---|---|---|
| 13 | **Billing otomatis** (Midtrans/Xendit) | Sengaja ditunda. Validasi manual lewat transfer masih sanggup selama pelanggan sedikit. |
| 14 | **Satu buku untuk beberapa orang** | Kasir + pemilik. Inilah yang membuat Laravel Reverb jadi berguna; sebelum ada kebutuhan ini, sinkron seketika dari server ke ponsel nilainya kecil. |
| 15 | **Grafik di laporan ponsel** | Sekarang hanya angka. |

---

## Yang sengaja tidak dikerjakan

- **Sinkron saat aplikasi tertutup penuh.** Butuh Android WorkManager, dan NativePHP tidak membuka pintunya ke PHP. Tidak terasa dalam praktik: aplikasi harus dibuka untuk mencatat apa pun, dan begitu dibuka ia menyinkron sendiri.
- **Lampiran PDF invoice lewat share sheet.** Dipakai link bertanda tangan 30 hari, bukan berkas. Lampiran butuh FileProvider yang belum bisa dipastikan tanpa uji perangkat.
