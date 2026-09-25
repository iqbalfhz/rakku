# RakKu — Panduan Rilis Android

Langkah menyiapkan aplikasi ponsel untuk Play Store. Ditulis untuk dikerjakan sambil dibaca, bukan untuk dibaca sekali lalu ditinggal.

Bagian **1** hanya dikerjakan sekali seumur aplikasi. Bagian **2** diulang setiap kali merilis versi baru.

---

## 1. Sekali seumur aplikasi

### 1.1 Keystore — baca ini dulu

Keystore adalah kunci yang membuktikan bahwa pembaruan aplikasi benar-benar datang dari Anda. Google memakai sidik jarinya untuk memastikan itu.

**Kalau keystore hilang, Anda tidak bisa lagi memperbarui RakKu di Play Store. Selamanya.** Bukan "harus menghubungi dukungan" — aplikasi lamanya tetap di sana, tapi Anda harus menerbitkan aplikasi baru dengan nama paket berbeda, dan seluruh pengguna beserta ulasannya tertinggal di aplikasi lama.

Karena itu:

- **Jangan pernah** memasukkan berkas `.jks` ke Git. Sudah dikunci di [`mobile/.gitignore`](../mobile/.gitignore), tapi jangan pernah memaksanya masuk dengan `git add -f`.
- **Cadangkan** berkas keystore **dan** keempat kata sandinya ke tempat yang tidak akan hilang bersama laptop ini — pengelola kata sandi, atau drive terenkripsi.
- Cadangkan **sebelum** rilis pertama, bukan setelahnya.

### 1.2 Peringatan: isi `mobile/.env` ikut masuk ke dalam APK

Ini penting dipahami sebelum membuat keystore.

Aplikasi ponsel ini menjalankan Laravel **di dalam** ponsel, jadi seluruh proyeknya — termasuk `.env` — dibungkus ke dalam APK. Daftar berkas yang dikeluarkan ada di [`BundleExclusions.php`](../mobile/vendor/nativephp/mobile/src/Support/BundleExclusions.php), dan di sana hanya ada `.env.example`, bukan `.env`.

APK bisa dibuka siapa saja dengan unzip biasa. **Jangan pernah menaruh rahasia di `mobile/.env`.**

Berkas `.jks` sendiri aman — `*.jks` ada di daftar yang dikeluarkan. Yang berbahaya adalah kata sandinya.

### 1.3 Membuat keystore

NativePHP punya perintahnya. Jalankan dari folder `mobile`:

```
php artisan native:credentials android
```

Perintah ini akan menanyakan:

| Pertanyaan | Saran |
|---|---|
| Keystore filename | biarkan `app-release-key.jks` |
| Key alias | biarkan `app-key` |
| Keystore password | buat yang panjang, simpan di pengelola kata sandi |
| Key password | boleh sama dengan di atas |
| Nama, organisasi, kota, negara | isi apa adanya; masuk ke sertifikat, tidak ditampilkan ke pengguna |

Hasilnya berkas keystore di `mobile/credentials/app-release-key.jks`.

**Perintah ini juga menulis keempat kata sandinya ke `mobile/.env`.** Karena alasan di bagian 1.2, hapus keempat baris itu setelah mencatatnya di tempat aman:

```
ANDROID_KEYSTORE_FILE=...
ANDROID_KEYSTORE_PASSWORD=...
ANDROID_KEY_ALIAS=...
ANDROID_KEY_PASSWORD=...
```

Variabel itu hanya **cadangan** bagi perintah build — lihat [`PackageCommand.php:215`](../mobile/vendor/nativephp/mobile/src/Commands/PackageCommand.php#L215). Yang didahulukan adalah opsi baris perintah, dan itulah cara yang dipakai di bagian 2.3.

**Cadangkan berkas `.jks` dan keempat kata sandinya sekarang juga**, ke tempat yang tidak hilang bersama laptop ini. Sebaiknya pindahkan `.jks`-nya keluar dari folder proyek sekalian.

### 1.4 Identitas aplikasi

Di `mobile/.env`, pastikan nama paketnya sudah final:

```
NATIVEPHP_APP_ID=id.my.iqbalfhz.rakku
```

**Nama paket tidak bisa diubah setelah rilis pertama** — ia melekat selamanya, dan menggantinya berarti menerbitkan aplikasi baru dari nol tanpa membawa pengguna maupun ulasan.

Aturannya: huruf kecil, minimal dua bagian dipisah titik, tiap bagian diawali huruf, tanpa tanda hubung, dan tidak boleh memakai kata kunci Java (`new`, `class`, `int`, `native`, dan sejenisnya).

Yang pernah terpasang di emulator adalah `com.loq.aurorasolarshine` — nama acak bawaan. Setelah diganti, aplikasi baru akan terpasang berdampingan dengan yang lama, bukan menimpanya. Hapus yang lama dengan `adb uninstall com.loq.aurorasolarshine`.

Tidak perlu menyentuh berkas lain: build mendeteksi nama lama di proyek Android dan menulis ulang sendiri ([`PreparesBuild.php:108`](../mobile/vendor/nativephp/mobile/src/Concerns/PreparesBuild.php#L108)). Jangan hapus folder `nativephp/` — itu hanya membuat build berikutnya jauh lebih lama.

### 1.5 Ikon dan splash screen

Sudah ada versi sementara, tinggal ditimpa saat desain final siap:

| Berkas | Syarat |
|---|---|
| `mobile/public/icon.png` | persegi, ≥ 1024×1024, PNG, **tanpa transparansi** |
| `mobile/public/splash.png` | potret 2:3 (mis. 1280×1920), untuk mode terang |
| `mobile/public/splash-dark.png` | ukuran sama, untuk mode gelap |

NativePHP mengecilkannya sendiri ke semua kerapatan layar. Tidak ada konfigurasi yang perlu disentuh.

Satu hal untuk desain ikon: Android memotong ikon jadi lingkaran atau kotak melengkung. Jaga elemen pentingnya di dalam **lingkaran tengah, 66% dari sisi** — di luar itu akan terpotong.

---

## 2. Setiap kali merilis

### 2.1 Naikkan versi

Di `mobile/.env`:

```
NATIVEPHP_APP_VERSION=1.0.0
NATIVEPHP_APP_VERSION_CODE=1
```

- `VERSION` yang dilihat pengguna. Gaya bebas, biasanya `1.0.0`.
- `VERSION_CODE` angka bulat yang **wajib naik setiap unggahan**. Play Store menolak berkas dengan angka yang sama atau lebih kecil. Naikkan satu tiap rilis: 1, 2, 3.

Versi ini juga ikut terkirim di setiap laporan kerusakan dari ponsel pengguna (lihat [`DiagnosticsReporter`](../mobile/app/Services/DiagnosticsReporter.php)), jadi menaikkannya bukan sekadar formalitas — itu yang membuat Anda tahu versi mana yang bermasalah.

### 2.2 Matikan mode debug

Di `mobile/.env`:

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

Tanpa ini, setiap kali aplikasi bermasalah di ponsel pengguna yang muncul adalah halaman error Laravel lengkap dengan jejak tumpukan dan isi konfigurasi. NativePHP tidak mengubahnya sendiri; ia hanya menolak menerbitkan ke jalur produksi ([`PublishesToPlayStore.php:24`](../mobile/vendor/nativephp/mobile/src/Concerns/PublishesToPlayStore.php#L24)), sementara APK-nya tetap terbangun dalam mode debug.

`LOG_LEVEL=debug` menulis setiap kejadian ke berkas log di dalam penyimpanan ponsel pengguna — menumpuk tanpa ada yang membacanya.

Kembalikan ketiganya ke nilai pengembangan setelah selesai merilis. Pengingatnya sudah ditulis sebagai komentar di `mobile/.env`.

### 2.3 Build

Keystore dan kata sandinya diberikan lewat baris perintah, bukan lewat `.env`:

```
php artisan native:build --release ^
  --keystore=C:/lokasi/aman/app-release-key.jks ^
  --keystore-password=KATA_SANDI_KEYSTORE ^
  --key-alias=app-key ^
  --key-password=KATA_SANDI_KUNCI
```

(`^` adalah penyambung baris di Command Prompt Windows. Di PowerShell pakai backtick, atau tulis semuanya dalam satu baris.)

Hasilnya berkas `.aab` (Android App Bundle) yang sudah ditandatangani. Itu yang diunggah ke Play Console.

**Catatan riwayat perintah:** kata sandi yang diketik di terminal tersimpan di riwayat shell. Kalau itu mengganggu, jalankan perintahnya dari skrip kecil yang tidak ikut Git, atau kembalikan variabel `.env` hanya selama build lalu hapus lagi.

### 2.4 Sebelum mengunggah, periksa

- [ ] Kedua suite tes lewat: `php artisan test` di root **dan** di `mobile`
- [ ] Sudah masuk, mencatat transaksi, dan sinkron di perangkat sungguhan
- [ ] Ikon muncul benar di peluncur
- [ ] Nomor versi sudah naik
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`
- [ ] Tidak ada rahasia tersisa di `mobile/.env`

---

## 3. Play Console

### 3.1 Yang wajib ada

| Bagian | Catatan |
|---|---|
| Kebijakan privasi | Sudah ada di `https://rakku.iqbalfhz.my.id/privasi` |
| Penghapusan akun | **Wajib dua jalur.** Di dalam aplikasi sudah ada di layar Akun saya; tautan webnya arahkan ke halaman profil di `/app`. |
| Data safety | Lihat di bawah |
| Tangkapan layar | Minimal 2, ukuran ponsel |
| Ikon toko | 512×512 PNG |
| Feature graphic | 1024×500 |

### 3.2 Data safety — isi jujur

RakKu mengumpulkan dan mengirim ke server Anda sendiri:

| Jenis data | Untuk apa | Wajib? |
|---|---|---|
| Nama & email | Akun dan verifikasi | Ya |
| Data keuangan (transaksi, utang, invoice) | Fungsi utama aplikasi | Ya |
| Foto (struk, bukti transfer, lampiran aduan) | Bukti transaksi dan dukungan | Tidak |
| Diagnostik kerusakan | Memperbaiki aplikasi | Tidak |

Semuanya dikirim terenkripsi lewat HTTPS, dan bisa dihapus pengguna sendiri lewat penghapusan akun. Tidak ada yang dibagikan ke pihak ketiga — jawab "tidak" untuk semua pertanyaan berbagi data.

**Jangan menjawab asal.** Google membandingkan jawaban ini dengan perilaku aplikasi yang sebenarnya, dan ketidakcocokan bisa membuat aplikasi ditarik.

### 3.3 Biaya

Pendaftaran akun pengembang Google Play: **$25**, sekali seumur hidup.

---

## 4. Kalau keystore terlanjur hilang

Masih ada satu jalan, tapi hanya kalau Anda memakai **Play App Signing** (aktif secara bawaan untuk aplikasi baru): minta reset kunci unggah ke Google lewat Play Console. NativePHP punya pembantunya:

```
php artisan native:credentials android --reset
```

Perintah itu membuat keystore baru beserta sertifikat PEM yang diminta Google saat mengajukan reset.

Ini bukan alasan untuk lalai mencadangkan — prosesnya makan waktu berhari-hari, dan hanya berlaku untuk kunci unggah, bukan kunci penandatangan aplikasi.
