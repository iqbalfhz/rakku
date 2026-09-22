# RakKu — Panduan Konfigurasi Email (SMTP)

Dokumen ini menjelaskan cara menghubungkan RakKu ke server email agar verifikasi akun, reset password, dan pengiriman invoice berfungsi di production.

---

## 1. Email apa saja yang dikirim RakKu

| Jenis | Pemicu | Penerima | Jalur |
|---|---|---|---|
| Verifikasi email | User mendaftar akun baru | User | Queue |
| Reset password | User klik "Lupa password" | User | Queue |
| Invoice + PDF | Tombol "Kirim via Email" di halaman Invoice | Klien pemilik buku | Queue |

Semuanya melewati **queue**, dan queue diproses oleh Scheduled Task `php artisan schedule:run` yang jalan tiap menit di Coolify. Kalau Scheduled Task mati, email tidak akan pernah terkirim walaupun SMTP-nya benar.

**Catatan alamat pengirim:** semua email keluar dari satu akun SMTP milik platform. Khusus invoice (lihat [`app/Mail/InvoiceMail.php`](../app/Mail/InvoiceMail.php)), klien melihat **nama buku** sebagai nama pengirim, dan `replyTo` diarahkan ke email pemilik buku sehingga balasan masuk ke inbox pemilik, bukan inbox platform. Alamat pengirimnya sendiri tetap `MAIL_FROM_ADDRESS` karena server SMTP hanya mengizinkan alamat yang terautentikasi.

---

## 2. Setup cepat: Gmail + App Password

Cocok untuk pemakaian pribadi atau saat pengguna masih sedikit. Kuota Gmail sekitar 500 email per hari.

### 2.1 Buat App Password

Password Gmail biasa **ditolak** oleh SMTP Google. Yang dipakai adalah App Password 16 karakter.

1. Buka **myaccount.google.com → Security**.
2. Aktifkan **2-Step Verification**. Tanpa ini, menu App Password tidak muncul.
3. Buka **myaccount.google.com/apppasswords**, beri nama `RakKu`, lalu **Create**.
4. Salin 16 karakter yang muncul dan **hapus spasinya**. Nilai ini hanya ditampilkan sekali.

### 2.2 Isi Environment Variables di Coolify

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=emailanda@gmail.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=emailanda@gmail.com
MAIL_FROM_NAME=RakKu
```

Dua kesalahan yang paling sering terjadi:

- **`MAIL_FROM_ADDRESS` harus sama persis dengan `MAIL_USERNAME`.** Gmail menolak mengirim email yang mengaku berasal dari alamat lain, kecuali alamat itu sudah didaftarkan sebagai alias di Gmail ("Send mail as").
- **`MAIL_PASSWORD` diisi App Password**, bukan password akun Google.

Untuk port 465, tambahkan `MAIL_SCHEME=smtps`. Untuk port 587 (default), `MAIL_SCHEME` dibiarkan kosong karena STARTTLS dinegosiasikan otomatis.

### 2.3 Restart

Setelah **Save**, jalankan **Restart** di Coolify. Konfigurasi dibaca dan di-cache saat container menyala, jadi perubahan env tidak berlaku sebelum restart.

---

## 3. Verifikasi bahwa SMTP sudah jalan

Buka **Terminal** aplikasi RakKu di Coolify:

```bash
php artisan tinker --execute 'Mail::raw("Tes email dari RakKu.", fn ($m) => $m->to("emailanda@gmail.com")->subject("Tes RakKu"));'
```

Perintah ini mengirim langsung tanpa queue, jadi hasilnya ketahuan seketika. Tidak ada output error berarti berhasil — cek inbox dan folder spam.

Uji alur sungguhan dengan mendaftar akun percobaan di `/app/register`. Email verifikasi datang paling lama sekitar 1 menit karena menunggu giliran queue.

### Troubleshooting

| Gejala | Penyebab |
|---|---|
| `Username and Password not accepted` | Masih memakai password akun, bukan App Password; atau App Password salah salin |
| `Connection could not be established` / timeout | Port diblokir. Coba `MAIL_PORT=465` + `MAIL_SCHEME=smtps` |
| `Sender address rejected` | `MAIL_FROM_ADDRESS` tidak sama dengan `MAIL_USERNAME` |
| Tes manual berhasil, tapi email registrasi tidak datang | Queue tidak jalan. Cek Scheduled Task di Coolify, lalu `php artisan queue:failed` |
| Email masuk ke spam | Wajar untuk domain gratisan. Solusinya naik ke bagian 4 |

Kalau ada user yang terlanjur tidak bisa login karena emailnya tidak pernah datang, admin bisa memakai aksi **"Verifikasi email manual"** di `/admin` → Users.

---

## 4. Upgrade: domain sendiri via Resend atau Brevo

Dipakai kalau RakKu sudah melayani pengguna lain. Keuntungannya: pengirim menjadi `noreply@iqbalfhz.my.id`, deliverability jauh lebih baik, ada log pengiriman, dan tidak memakai kuota Gmail pribadi.

Langkahnya:

1. Daftar di Resend atau Brevo, lalu tambahkan domain `iqbalfhz.my.id`.
2. Salin record DNS yang mereka berikan (SPF dan DKIM, kadang satu CNAME) ke Cloudflare DNS. Tunggu sampai statusnya **Verified**.
3. Ganti env di Coolify, contoh untuk Resend:

   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.resend.com
   MAIL_PORT=587
   MAIL_USERNAME=resend
   MAIL_PASSWORD=re_xxxxxxxxxxxx
   MAIL_FROM_ADDRESS=noreply@iqbalfhz.my.id
   MAIL_FROM_NAME=RakKu
   ```

4. **Restart**, lalu ulangi pengujian di bagian 3.

Tanpa SPF/DKIM yang valid, mengirim email yang mengaku dari domain sendiri justru membuat email masuk spam atau ditolak. Jadi urutannya selalu: verifikasi domain dulu, baru ganti `MAIL_FROM_ADDRESS`.

---

## 5. Kenapa SMTP tidak disetel per pengguna di `/app`

Pernah dipertimbangkan agar tiap pengguna memasukkan SMTP-nya sendiri supaya invoice terkirim dari email mereka. Keputusan saat ini: **tidak**, dengan alasan:

- Verifikasi email dan reset password **wajib** memakai SMTP platform, karena dibutuhkan sebelum pengguna sempat mengisi apa pun.
- Menyimpan kredensial SMTP pengguna menuntut enkripsi, tombol tes kirim, penanganan kredensial kedaluwarsa, dan pemblokiran host internal agar tidak jadi celah SSRF.
- Kebutuhan "invoice terasa dari saya" sudah tertutup oleh `replyTo` yang mengarah ke email pemilik buku, dan oleh pengiriman via WhatsApp yang memang memakai nomor pengguna sendiri.

Kalau nanti dibuka, bentuknya sebagai fitur premium per buku dengan fallback ke SMTP platform bila pengiriman gagal.
