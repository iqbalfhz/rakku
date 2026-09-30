# RakKu — Rencana Lanjutan

Daftar pekerjaan yang tersisa menuju rilis publik, disusun pada **24 September 2026** setelah seluruh fitur premium selesai dibawa ke aplikasi ponsel.

Urutannya bukan urutan besar-kecil, melainkan urutan **mahal-murahnya diperbaiki belakangan**. Yang sudah terlanjur ada di ponsel orang lain jauh lebih mahal diperbaiki daripada yang masih di laptop.

---

## A. Menghalangi masuk Play Store

| # | Pekerjaan | Kenapa mendesak |
|---|---|---|
| 1 | ~~**Hapus akun dari dalam aplikasi**~~ — **selesai 24 Sep 2026** | Google **mewajibkan** aplikasi dengan pendaftaran akun menyediakan penghapusan akun dari dalam aplikasi *dan* lewat tautan web. Sekarang [`DeleteAccountAction`](../app/Filament/App/Actions/DeleteAccountAction.php) hanya ada di panel web. Tanpa ini, review ditolak. |
| 2 | ~~**Ikon dan splash screen**~~ — **selesai 24 Sep 2026** | Versi sementara sudah terpasang: `mobile/public/icon.png`, `splash.png`, `splash-dark.png`. Ganti berkasnya saja dengan desain final; syaratnya ada di [panduan rilis](panduan-rilis-android.md#14-ikon-dan-splash-screen). |
| 3 | **Keystore & build rilis bertanda tangan** — *langkahnya sudah ditulis, tinggal dijalankan* | Ikuti [panduan rilis Android](panduan-rilis-android.md). Folder `credentials/` dan berkas `.jks` sudah dikunci dari Git lebih dulu. `NATIVEPHP_APP_ID` sudah final: `id.my.iqbalfhz.rakku` — **tidak bisa diubah setelah rilis pertama**. |
| 4 | **Data safety form** — *jawabannya sudah disiapkan* | Tabel isiannya ada di [panduan rilis](panduan-rilis-android.md#32-data-safety--isi-jujur). |

---

## B. Lubang yang tersisa di aplikasi ponsel

| # | Pekerjaan | Catatan |
|---|---|---|
| 5 | ~~**Transfer antar akun**~~ — **selesai 24 Sep 2026** | Server punya [`Transfer`](../app/Models/Transfer.php), ponsel tidak. Terlewat waktu memetakan fitur premium — transfer memang bukan premium. Tanpa ini, memindahkan uang laci ke rekening harus ditulis sebagai dua transaksi terpisah, dan laporan jadi salah karena keduanya terhitung pemasukan dan pengeluaran sungguhan. |
| 6 | ~~**Pendaftaran akun**~~ — **selesai 24 Sep 2026** | Satu-satunya kalimat tersisa di aplikasi yang menyuruh membuka browser. |
| 7 | ~~**Tiket dukungan**~~ — **selesai 24 Sep 2026** | Semula diputuskan tetap di web. Keputusan itu ditarik: pengguna yang aplikasinya bermasalah justru saat itulah paling butuh mengadu. |
| 8 | ~~**Cari dan saring di riwayat**~~ — **selesai 24 Sep 2026** | Setelah ratusan catatan, layar riwayat jadi gulungan panjang tanpa alat bantu. |

---

## C. Ketahanan sebelum dipakai orang lain

| # | Pekerjaan | Catatan |
|---|---|---|
| 9 | ~~**Mata untuk server**~~ — **selesai 24 Sep 2026** | Ponsel sudah mengabarkan kerusakannya lewat [`DiagnosticsReporter`](../mobile/app/Services/DiagnosticsReporter.php); server masih diam — hanya `laravel.log` yang tidak ada yang membaca. |
| 10 | ~~**Backup off-site**~~ — **selesai 25 Sep 2026** | `league/flysystem-aws-s3-v3` sudah terpasang, jadi `BACKUP_OFFSITE_DISK=s3` sekarang bisa dipakai. Salinannya diverifikasi (ada dan seukuran) dan setiap kegagalan dikirim ke admin lewat [`BackupFailed`](../app/Notifications/BackupFailed.php) — sebelumnya salinan yang ditolak penyimpanan tetap dilaporkan berhasil. Keduanya sudah berjalan di produksi ke bucket Cloudflare R2 `rakku-backup`: arsip file unggahan lewat perintah ini, dan dump MySQL lewat backup terjadwal Coolify (*Keep local backup* + S3, retensi 14). **Sudah diuji pulih 30 Sep 2026**: dump 30 Sep 02:00 diunduh dari R2, dipulihkan ke resource MySQL sekali pakai di Coolify (bukan ke produksi), menghasilkan 30 tabel dan 8 transaksi yang cocok baris per baris dengan yang terbaca di aplikasi. Jalur lengkapnya terbukti — produksi → dump → R2 → pemulihan. Ulangi sesekali, terutama setelah perubahan besar pada skema. |
| 11 | ~~**Uptime monitoring**~~ — **selesai 30 Sep 2026** | Rute [`/sehat`](../routes/web.php) memeriksa database menjawab dan penyimpanan unggahan bisa ditulisi, membalas 200 atau 503 — arahkan layanan pemantau ke sana. `/up` sengaja **tidak** diubah: ia dipakai healthcheck Docker, dan kalau ikut gagal saat MySQL berkedip, container-nya di-restart berulang justru saat keadaan sedang buruk. Ada tes khusus yang menjaga pemisahan itu. Yang tersisa hanya mendaftarkan layanan pemantaunya. |
| 12 | ~~**Masa berlaku token Sanctum**~~ — **selesai 24 Sep 2026** | Diselesaikan bukan dengan masa berlaku, melainkan dengan daftar perangkat yang bisa dicabut (`/akun` di ponsel). Token sengaja tetap tanpa kedaluwarsa: aplikasi ini dipakai tanpa sinyal, dan token yang mati di lapangan berarti pengguna tidak bisa membuka bukunya sendiri. |
| 16 | ~~**Tes berjalan di MySQL seperti produksi**~~ — **selesai 25 Sep 2026** | Seluruh suite dulu hanya jalan di SQLite di memori, sementara produksi memakai MySQL. Keduanya berbeda di hal yang tidak kelihatan — `ONLY_FULL_GROUP_BY`, mode ketat yang menolak nilai terlalu panjang alih-alih memotongnya, perbandingan huruf besar-kecil, pembulatan desimal — jadi bug yang hanya muncul di MySQL akan lolos semua tes lalu mengenai data sungguhan. Sekarang ada [`phpunit.mysql.xml`](../phpunit.mysql.xml): `composer run test:mysql`. Saat dijalankan pertama kali, 429 tes lolos semua — celahnya baru berupa risiko, belum jadi bug. SQLite tetap dipakai sehari-hari karena lebih cepat (57 detik lawan 81); MySQL dijalankan sebelum deploy. |
| — | **Jebakan `native:run`: salinan di ponsel tidak ikut diperbarui** | Memasang ulang APK **tidak menyegarkan** `app_storage/laravel` yang sudah terekstrak. Yang tersalin hanyalah berkas yang diubah **selagi `--watch` berjalan**. Akibatnya perubahan yang dibuat saat watcher mati akan tertinggal di ponsel selamanya, walau sudah build ulang berkali-kali — dan ponsel menjalankan campuran kode baru dengan kode lama. Terbukti 29 Sep 2026: `AppServiceProvider.php` di perangkat masih versi 24 Sep setelah beberapa kali build. Kalau ragu, copot dulu aplikasinya (`adb uninstall`) lalu pasang lagi; data di ponsel akan pulih sendiri dari server setelah masuk. `watch_paths` di `config/nativephp.php` juga sempat kehilangan `database` dan `bootstrap` — sudah diperbaiki dan dijaga [`HotReloadConfigTest`](../mobile/tests/Feature/HotReloadConfigTest.php). |
| — | **Jebakan `--watch`: menambah kelas baru merusak aplikasi yang sedang jalan** | Autoloader di APK dibangun `--classmap-authoritative`, jadi kelas yang tidak ada di peta saat build **tidak akan pernah ditemukan**. Watcher menyalin berkas kelas barunya ke ponsel, tapi tidak bisa memperbarui petanya — akibatnya berkas yang memakainya ikut tersalin lebih dulu dan aplikasinya mati dengan `Target class [...] does not exist`. Terjadi 30 Sep 2026 saat `DeviceFacts` ditambahkan. **Aturannya: menambah kelas berarti build ulang, bukan mengandalkan watcher.** Kalau terlanjur dan butuh perbaikan cepat, tambahkan entrinya ke `vendor/composer/autoload_static.php` di perangkat — bukan `autoload_classmap.php`, yang tidak dibaca saat classmap dioptimalkan. |
| — | **Pola backfill kolom baru** — *diukur 30 Sep 2026* | Delapan migrasi mengisi `public_id` satu baris per satu `UPDATE`. Diukur di MySQL 8.4: **14,5 detik lawan 0,14 detik untuk 5.000 baris — 102× lebih lambat.** Tapi kedelapannya **sudah jalan di produksi dan tidak akan terulang**, dan pada pemasangan baru tabelnya kosong; menulis ulangnya nol manfaat. Yang berlaku adalah untuk migrasi **berikutnya** yang mengisi kolom baru di tabel yang sudah berisi: jangan `pluck('id')->each(fn () => update())`, tapi `array_chunk` per ~500 lalu satu `UPDATE ... SET kolom = CASE id WHEN ? THEN ? ... END WHERE id IN (...)` per kelompok. |

---

## D. Fitur baru

| # | Pekerjaan | Catatan |
|---|---|---|
| 13 | **Billing otomatis** (Midtrans/Xendit) | Sengaja ditunda. Validasi manual lewat transfer masih sanggup selama pelanggan sedikit. |
| 14 | **Satu buku untuk beberapa orang** | Kasir + pemilik. Inilah yang membuat Laravel Reverb jadi berguna; sebelum ada kebutuhan ini, sinkron seketika dari server ke ponsel nilainya kecil. |
| 15 | ~~**Grafik di laporan ponsel**~~ — **selesai 25 Sep 2026** | Tren enam bulan (batang masuk/keluar per bulan) dan batang porsi di tiap baris kategori. Digambar dengan CSS biasa dari sisi PHP — tanpa pustaka JS dan tanpa proses build, supaya laporan tetap terbuka penuh tanpa sinyal. Warna markanya sengaja tidak memakai `--accent` dan `--debit` apa adanya: keduanya terlalu pucat sebagai petak kecil dan pasangannya gagal uji buta warna, jadi dipakai `--chart-income` dan `--chart-expense` yang divalidasi terpisah untuk mode terang dan gelap. Bulan bernilai nol sengaja tidak digambar sama sekali — batang setipis apa pun terbaca sebagai "ada uang masuk". |

---

## Yang sengaja tidak dikerjakan

- **Sinkron saat aplikasi tertutup penuh.** Butuh Android WorkManager, dan NativePHP tidak membuka pintunya ke PHP. Tidak terasa dalam praktik: aplikasi harus dibuka untuk mencatat apa pun, dan begitu dibuka ia menyinkron sendiri.
- **Lampiran PDF invoice lewat share sheet.** Dipakai link bertanda tangan 30 hari, bukan berkas. Lampiran butuh FileProvider yang belum bisa dipastikan tanpa uji perangkat.
