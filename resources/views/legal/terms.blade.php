<x-layouts.public
    title="Syarat Layanan — RakKu"
    description="Aturan pemakaian RakKu: apa yang kami sediakan, apa yang menjadi tanggung jawab Anda, dan bagaimana langganan premium bekerja."
    :login-url="$loginUrl"
    :register-url="$registerUrl"
>
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20">
        <p class="text-xs tracking-widest text-ink-soft uppercase">Terakhir diperbarui {{ $updatedAt }}</p>
        <h1 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">Syarat Layanan</h1>
        <p class="mt-6 text-lg leading-relaxed text-ink-soft">
            Dengan membuat akun RakKu, Anda setuju dengan ketentuan di halaman ini.
            Kami tulis sependek dan sejelas mungkin.
        </p>

        <div class="mt-12 space-y-10">
            <section>
                <h2 class="font-serif text-2xl">Apa itu RakKu</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    RakKu adalah aplikasi pencatatan keuangan. RakKu <span class="font-medium text-ink">bukan</span> bank,
                    bukan dompet digital, dan bukan penyedia jasa pembayaran. Aplikasi ini tidak menyimpan maupun
                    memindahkan uang sungguhan; ia hanya mencatat angka yang Anda masukkan.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Akun Anda</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Satu akun untuk satu orang atau satu usaha. Jaga kerahasiaan kata sandi Anda, karena segala kegiatan
                    yang terjadi lewat akun Anda menjadi tanggung jawab Anda. Beri tahu kami kalau Anda menduga akun
                    Anda dipakai orang lain.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Tanggung jawab atas angka</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Semua catatan, invoice, dan laporan dihitung dari data yang Anda masukkan sendiri. Ketepatannya
                    menjadi tanggung jawab Anda, termasuk saat dipakai untuk pelaporan pajak atau penagihan ke klien.
                    Periksa kembali angkanya sebelum dipakai untuk keputusan penting.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Langganan premium</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Fitur harian gratis selamanya. Fitur premium dibuka dengan membayar di muka melalui transfer,
                    lalu mengunggah bukti transfernya. Premium menyala setelah kami memverifikasi pembayaran secara manual,
                    dan berlaku selama jangka waktu paket yang Anda pilih.
                </p>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Tidak ada tagihan berulang dan tidak ada perpanjangan otomatis. Saat masa aktif habis, fitur premium
                    terkunci tetapi seluruh catatan Anda tetap tersimpan dan tetap bisa dibuka serta diekspor.
                    Pembayaran yang sudah diverifikasi tidak dapat dikembalikan, kecuali kegagalan layanan memang
                    berasal dari pihak kami.
                </p>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Harga dapat berubah sewaktu-waktu. Perubahan harga hanya berlaku untuk pembelian berikutnya;
                    masa aktif yang sudah Anda bayar tidak terpengaruh.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Pemakaian yang tidak diperbolehkan</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Jangan memakai RakKu untuk kegiatan melanggar hukum, mengunggah berkas berbahaya, mencoba masuk
                    ke akun orang lain, atau membebani layanan secara tidak wajar. Kami dapat menangguhkan akun
                    yang melakukannya, dan akan memberi tahu Anda alasannya.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Ketersediaan layanan</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Kami berusaha menjaga RakKu tetap berjalan dan mencadangkan data setiap hari, tetapi layanan
                    diberikan apa adanya tanpa jaminan bebas gangguan. Sewaktu-waktu layanan bisa berhenti sementara
                    karena pemeliharaan atau hal di luar kendali kami. Karena itu, ekspor data Anda secara berkala
                    kalau catatan tersebut penting bagi usaha Anda.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Menghentikan pemakaian</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Anda bebas berhenti kapan saja. Ekspor dulu data Anda, lalu hubungi kami untuk menghapus akun
                    beserta seluruh isinya. Kami juga dapat menutup akun yang melanggar ketentuan di halaman ini.
                </p>
            </section>

            <section>
                <h2 class="font-serif text-2xl">Perubahan ketentuan</h2>
                <p class="mt-4 leading-relaxed text-ink-soft">
                    Ketentuan ini bisa diperbarui seiring perkembangan aplikasi. Tanggal pembaruan terakhir
                    tercantum di bagian atas halaman, dan perubahan penting akan kami sampaikan lewat notifikasi di aplikasi.
                </p>
            </section>
        </div>
    </article>
</x-layouts.public>
