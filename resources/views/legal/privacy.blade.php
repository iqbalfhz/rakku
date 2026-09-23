@use('App\Support\PublicContact')

<x-layouts.public
    title="Kebijakan Privasi — RakKu"
    description="Data apa saja yang RakKu simpan, siapa yang bisa melihatnya, dan hak Anda atas data itu."
    :login-url="$loginUrl"
    :register-url="$registerUrl"
>
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20">
        <p class="text-xs tracking-widest text-ink-soft uppercase">Terakhir diperbarui {{ $updatedAt }}</p>
        <h1 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">Kebijakan Privasi</h1>
        <p class="mt-6 text-lg leading-relaxed text-ink-soft">
            RakKu menyimpan catatan keuangan Anda. Halaman ini menjelaskan apa saja yang kami simpan,
            siapa yang bisa melihatnya, dan apa yang bisa Anda minta dari kami.
        </p>

        <div class="mt-12 space-y-10">
            <section>
                <h2 class="font-serif text-2xl">Data yang kami simpan</h2>
                <ul class="mt-4 space-y-2 leading-relaxed text-ink-soft">
                    <li>— Nama dan alamat email yang Anda isi saat mendaftar.</li>
                    <li>— Catatan keuangan yang Anda masukkan: transaksi, akun, kategori, budget, utang-piutang, klien, dan invoice.</li>
                    <li>— Berkas yang Anda unggah: foto struk, bukti transfer langganan, dan lampiran tiket bantuan.</li>
                    <li>— Isi tiket bantuan yang Anda kirim.</li>
                    <li>— Catatan teknis seperti waktu masuk terakhir, yang muncul otomatis saat aplikasi dipakai.</li>
                </ul>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Kami tidak meminta data kartu, PIN, atau akses ke rekening bank Anda, dan tidak pernah membutuhkannya.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Cara kami memakainya</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Data Anda dipakai untuk menjalankan aplikasi itu sendiri: menghitung saldo dan laporan, mengirim
                    pengingat jatuh tempo, menerbitkan invoice, memverifikasi pembayaran premium, dan menjawab tiket bantuan.
                    Kami tidak menjual data Anda, tidak menukarnya, dan tidak memakainya untuk iklan.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Siapa yang bisa melihat</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Catatan keuangan Anda hanya terlihat oleh akun Anda sendiri. Admin dapat melihat data akun Anda
                    seperti nama, email, dan status langganan, serta isi tiket bantuan dan bukti transfer yang Anda kirim —
                    itu diperlukan untuk menjawab keluhan dan memverifikasi pembayaran. Admin tidak membuka transaksi Anda
                    kecuali Anda sendiri yang meminta bantuan atas suatu transaksi.
                </p>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Invoice yang Anda kirim ke klien memakai tautan bertanda tangan yang kedaluwarsa dalam 30 hari.
                    Siapa pun yang memegang tautan itu dapat membuka PDF-nya selama masih berlaku, jadi kirimkan hanya
                    kepada penerima yang Anda tuju.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Tempat penyimpanan dan keamanan</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Data disimpan di server yang kami kelola sendiri, diakses lewat sambungan terenkripsi (HTTPS).
                    Kata sandi disimpan dalam bentuk acak satu arah, sehingga tidak bisa dibaca siapa pun, termasuk kami.
                    Berkas unggahan disimpan di area privat, bukan folder publik. Basis data dan berkas dicadangkan setiap hari.
                </p>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Email keluar, seperti verifikasi akun dan invoice, dikirim lewat layanan email pihak ketiga.
                    Isi email itu tentu melewati layanan tersebut.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Berapa lama disimpan</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Data Anda disimpan selama akun masih ada, termasuk saat premium tidak diperpanjang.
                    Berkas hasil export dibersihkan otomatis setelah 90 hari karena bisa dibuat ulang kapan saja.
                    Jika Anda meminta akun dihapus, seluruh catatan dan berkas Anda ikut dihapus.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Hak Anda</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Anda bisa mengekspor transaksi Anda sendiri kapan saja dari dalam aplikasi. Anda juga berhak meminta
                    salinan data, perbaikan data yang keliru, atau penghapusan akun beserta isinya. Untuk penghapusan akun,
                    hubungi kami — permintaan itu kami kerjakan secara manual dan tidak bisa dibatalkan setelah dijalankan.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Menghubungi kami</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Kalau ada yang ingin ditanyakan soal data Anda, kirim tiket bantuan dari dalam aplikasi
                    @if (PublicContact::whatsAppUrl() !== null || PublicContact::email() !== null)
                        atau hubungi kami lewat kontak yang tertera di bagian bawah halaman ini.
                    @else
                        dan kami akan menjawabnya di sana.
                    @endif
                </p>
            </section>
        </div>
    </article>
</x-layouts.public>
