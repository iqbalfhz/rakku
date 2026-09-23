<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionPackage;
use App\Support\Rupiah;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Halaman depan untuk tamu; pengguna yang sudah masuk langsung dibawa ke bukunya.
 */
class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->to(filament()->getPanel('app')->getUrl());
        }

        return view('landing', [
            'loginUrl' => filament()->getPanel('app')->getLoginUrl(),
            'registerUrl' => filament()->getPanel('app')->getRegistrationUrl(),
            'packages' => $this->packages(),
            'features' => $this->features(),
            'steps' => $this->steps(),
            'promises' => $this->promises(),
            'questions' => $this->questions(),
            'ledgerRows' => $this->ledgerRows(),
            'ledgerBalance' => Rupiah::format(4_905_000),
        ]);
    }

    /**
     * Harga mengikuti pengaturan admin, jadi halaman depan tidak pernah basi.
     *
     * @return list<array{months: int, price: string, pricePerMonth: string, isBestValue: bool}>
     */
    private function packages(): array
    {
        return collect(SubscriptionPackage::cases())
            ->map(fn (SubscriptionPackage $package): array => [
                'months' => $package->months(),
                'price' => Rupiah::format($package->price()),
                'pricePerMonth' => Rupiah::format((int) round($package->price() / $package->months())),
                'isBestValue' => $package === SubscriptionPackage::Yearly,
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, isFree: bool}>
     */
    private function features(): array
    {
        return [
            ['name' => 'Catat pemasukan & pengeluaran + foto struk', 'isFree' => true],
            ['name' => 'Banyak akun dan dompet, lengkap dengan transfer', 'isFree' => true],
            ['name' => 'Laporan cash flow bulanan dan tahunan', 'isFree' => true],
            ['name' => 'Budget per kategori', 'isFree' => true],
            ['name' => 'Insight pengeluaran terbesar', 'isFree' => true],
            ['name' => 'Export data ke CSV', 'isFree' => true],
            ['name' => 'Utang-piutang + pengingat jatuh tempo', 'isFree' => false],
            ['name' => 'Invoice ke klien + PDF siap kirim', 'isFree' => false],
            ['name' => 'Laporan laba-rugi', 'isFree' => false],
            ['name' => 'Transaksi berulang otomatis', 'isFree' => false],
            ['name' => 'Peringatan saat budget hampir habis', 'isFree' => false],
            ['name' => 'Insight tren & perbandingan antar bulan', 'isFree' => false],
            ['name' => 'Export Excel format lengkap', 'isFree' => false],
            ['name' => 'Buku kedua dan seterusnya', 'isFree' => false],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function steps(): array
    {
        return [
            [
                'title' => 'Catat',
                'body' => 'Masukkan uang masuk dan keluar beserta kategorinya. Foto struk boleh ikut, kalau perlu bukti.',
            ],
            [
                'title' => 'Pantau',
                'body' => 'Saldo tiap akun dihitung sendiri. Laporan cash flow dan grafiknya ikut terbentuk tanpa diminta.',
            ],
            [
                'title' => 'Tagih',
                'body' => 'Buat invoice untuk klien, kirim PDF-nya lewat WhatsApp atau email, lalu tandai lunas saat dibayar.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function promises(): array
    {
        return [
            [
                'title' => 'Bukan aplikasi dompet',
                'body' => 'RakKu tidak memegang uang Anda. Ia mencatat angka yang mencerminkan uang sungguhan di kas dan rekening.',
            ],
            [
                'title' => 'Pisahkan usaha & pribadi',
                'body' => 'Satu akun bisa punya beberapa buku, jadi belanja dapur tidak tercampur dengan kas usaha.',
            ],
            [
                'title' => 'Catatan tetap milik Anda',
                'body' => 'Semua data bisa diekspor kapan saja, dan tetap bisa dibuka meski premium tidak diperpanjang.',
            ],
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function questions(): array
    {
        return [
            [
                'question' => 'Kalau premium berakhir, data saya hilang?',
                'answer' => 'Tidak. Semua catatan, foto struk, dan invoice Anda tetap tersimpan dan tetap bisa dibuka. Yang terkunci hanya fitur premiumnya, dan terbuka lagi begitu diperpanjang.',
            ],
            [
                'question' => 'Bagaimana cara membayarnya?',
                'answer' => 'Transfer ke rekening yang tertera di halaman Langganan, lalu unggah bukti transfernya. Kami periksa secara manual, biasanya tidak lama, dan premium menyala setelah disetujui.',
            ],
            [
                'question' => 'Apakah langganannya otomatis memperpanjang?',
                'answer' => 'Tidak. Tidak ada tagihan berulang dan tidak ada kartu yang disimpan. Premium berhenti sendiri saat masa aktifnya habis, dan Anda kami ingatkan seminggu sebelumnya.',
            ],
            [
                'question' => 'Apakah RakKu menyimpan uang saya?',
                'answer' => 'Tidak. RakKu hanya mencatat angka yang mencerminkan uang sungguhan di kas dan rekening Anda. Tidak ada uang yang masuk atau keluar lewat aplikasi ini.',
            ],
            [
                'question' => 'Bisa dipakai untuk usaha dan keuangan pribadi sekaligus?',
                'answer' => 'Bisa. Satu akun boleh punya beberapa buku yang angkanya terpisah, misalnya "Pribadi" dan nama usaha Anda. Buku pertama gratis, buku kedua dan seterusnya ikut premium.',
            ],
            [
                'question' => 'Data saya bisa dibawa keluar?',
                'answer' => 'Bisa kapan saja. Transaksi dapat diekspor ke CSV, dan pengguna premium mendapat format Excel yang lebih lengkap.',
            ],
        ];
    }

    /**
     * Contoh isi buku kas untuk ilustrasi di bagian atas halaman.
     *
     * @return list<array{date: string, label: string, amount: string, isIncome: bool}>
     */
    private function ledgerRows(): array
    {
        return [
            ['date' => '2 Okt', 'label' => 'Fotocopy & jilid', 'amount' => Rupiah::format(375_000), 'isIncome' => true],
            ['date' => '2 Okt', 'label' => 'Beli kertas A4 satu rim', 'amount' => Rupiah::format(120_000), 'isIncome' => false],
            ['date' => '3 Okt', 'label' => 'Invoice INV-2026-0007 lunas', 'amount' => Rupiah::format(1_250_000), 'isIncome' => true],
        ];
    }
}
