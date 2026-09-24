<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\ApiClient;
use App\Services\LedgerWriter;
use App\Services\TokenStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Native\Mobile\Facades\Camera;

new class extends Component
{
    public string $type = 'expense';

    public string $amount = '';

    public string $accountPublicId = '';

    public string $categoryPublicId = '';

    public string $description = '';

    public string $transactionDate = '';

    public ?string $receiptPath = null;

    public ?Transaction $transaction = null;

    public ?string $downloadError = null;

    public function mount(?string $publicId = null): void
    {
        $this->transaction = $publicId === null
            ? null
            : Transaction::query()->visible()->where('public_id', $publicId)->firstOrFail();

        if ($this->transaction === null) {
            $this->transactionDate = today()->toDateString();
            $this->accountPublicId = (string) Account::query()->visible()->value('public_id');

            return;
        }

        $this->type = $this->transaction->type;
        $this->amount = (string) (int) $this->transaction->amount;
        $this->accountPublicId = $this->transaction->account_public_id;
        $this->categoryPublicId = (string) $this->transaction->category_public_id;
        $this->description = (string) $this->transaction->description;
        $this->transactionDate = $this->transaction->transaction_date->toDateString();
    }

    public function isEditing(): bool
    {
        return $this->transaction !== null;
    }

    /**
     * Buka kamera bawaan ponsel. Hasilnya datang lewat event, bukan nilai balik.
     */
    public function takePhoto(): void
    {
        Camera::getPhoto();
    }

    /**
     * Foto disalin ke penyimpanan aplikasi supaya umurnya kita yang menentukan,
     * bukan bergantung pada folder sementara milik kamera.
     */
    #[On('native:Native\Mobile\Events\Camera\PhotoTaken')]
    public function photoTaken(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $storedPath = 'receipts/'.Str::lower((string) Str::ulid()).'.jpg';

        Storage::disk('local')->put($storedPath, file_get_contents($path));

        $this->receiptPath = $storedPath;
    }

    /**
     * Foto lama tersimpan di server, bukan di ponsel ini. Diambil saat diminta saja,
     * supaya membuka layar ubah tidak menyedot kuota tanpa alasan.
     */
    public function downloadPhoto(ApiClient $apiClient, TokenStore $tokenStore): void
    {
        if ($this->transaction === null || ! $this->transaction->has_receipt) {
            return;
        }

        try {
            $contents = $apiClient->downloadReceipt((string) $tokenStore->bookPublicId(), $this->transaction->public_id);
        } catch (\Throwable) {
            $this->downloadError = 'Foto gagal diambil. Coba lagi saat sinyal membaik.';

            return;
        }

        $storedPath = 'receipts/'.Str::lower((string) Str::ulid()).'.jpg';
        Storage::disk('local')->put($storedPath, $contents);

        $this->transaction->update(['receipt_local_path' => $storedPath]);
        $this->transaction->refresh();
    }

    public function photoDataUri(): ?string
    {
        $localPath = $this->receiptPath ?? $this->transaction?->receipt_local_path;

        if ($localPath === null || ! Storage::disk('local')->exists($localPath)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($localPath));
    }

    public function removePhoto(): void
    {
        if ($this->receiptPath !== null) {
            Storage::disk('local')->delete($this->receiptPath);
        }

        $this->receiptPath = null;
    }

    /**
     * Simpan ke ponsel dulu. Pengiriman ke server menyusul saat ada sinyal,
     * jadi tombol ini tidak pernah gagal karena koneksi.
     */
    public function save(LedgerWriter $ledgerWriter): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:1'],
            'accountPublicId' => ['required', 'exists:accounts,public_id'],
            'categoryPublicId' => ['nullable', 'exists:categories,public_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'transactionDate' => ['required', 'date'],
        ], attributes: [
            'amount' => 'nominal',
            'accountPublicId' => 'akun',
            'categoryPublicId' => 'kategori',
            'description' => 'keterangan',
            'transactionDate' => 'tanggal',
        ]);

        $attributes = [
            'account_public_id' => $data['accountPublicId'],
            'category_public_id' => $data['categoryPublicId'] ?: null,
            'type' => $data['type'],
            'amount' => (float) $data['amount'],
            'description' => $data['description'] ?: null,
            'transaction_date' => $data['transactionDate'],
            'receipt_local_path' => $this->receiptPath,
        ];

        $this->transaction === null
            ? $ledgerWriter->record($attributes)
            : $ledgerWriter->revise($this->transaction, $attributes);

        $this->redirect(route('home'));
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    /**
     * Kategori mengikuti jenis transaksi yang sedang dipilih.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->visible()->where('type', $this->type)->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        $this->categoryPublicId = '';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">{{ $this->isEditing() ? 'Ubah catatan' : 'Catatan baru' }}</p>
        <h1 class="masthead__title">{{ $this->isEditing() ? 'Perbaiki' : 'Tulis' }}<br>transaksi.</h1>
        <p class="masthead__note">Tersimpan di ponsel walau sedang tanpa sinyal.</p>
    </header>

    <form wire:submit="save">
        <div class="field">
            <span class="field__label">Jenis</span>
            <div class="choice">
                <label class="choice__option {{ $type === 'expense' ? 'choice__option--on' : '' }}">
                    <input type="radio" value="expense" wire:model.live="type"> Pengeluaran
                </label>
                <label class="choice__option {{ $type === 'income' ? 'choice__option--on' : '' }}">
                    <input type="radio" value="income" wire:model.live="type"> Pemasukan
                </label>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="amount">Nominal</label>
            <input class="field__input field__input--numeral" id="amount" type="number" inputmode="numeric"
                   min="1" step="1" placeholder="0" wire:model="amount">
            @error('amount') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field__label" for="account">Akun</label>
            <select class="field__input" id="account" wire:model="accountPublicId">
                @foreach ($this->accounts() as $account)
                    <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                @endforeach
            </select>
            @error('accountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field__label" for="category">Kategori</label>
            <select class="field__input" id="category" wire:model="categoryPublicId">
                <option value="">Tanpa kategori</option>
                @foreach ($this->categories() as $category)
                    <option value="{{ $category->public_id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @error('categoryPublicId') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field__label" for="date">Tanggal</label>
            <input class="field__input" id="date" type="date" wire:model="transactionDate">
            @error('transactionDate') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field__label" for="description">Keterangan</label>
            <input class="field__input" id="description" type="text" placeholder="Beli kertas A4"
                   wire:model="description">
            @error('description') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <span class="field__label">Foto struk</span>

            @if ($downloadError)
                <p class="notice">{{ $downloadError }}</p>
            @endif

            @if ($this->photoDataUri())
                <img class="receipt" src="{{ $this->photoDataUri() }}" alt="Foto struk">

                <div class="entry" style="border-bottom: 0;">
                    <span class="stamp">Foto terpasang</span>
                    <button class="linkish" type="button" wire:click="removePhoto">Ganti foto</button>
                </div>
            @elseif ($this->isEditing() && $transaction->has_receipt)
                <button class="button button--quiet" type="button" wire:click="downloadPhoto" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="downloadPhoto">Lihat foto struk</span>
                    <span wire:loading wire:target="downloadPhoto">Mengambil foto…</span>
                </button>
            @else
                <button class="button button--quiet" type="button" wire:click="takePhoto">Ambil foto struk</button>
            @endif
        </div>

        <button class="button" type="submit">{{ $this->isEditing() ? 'Simpan perubahan' : 'Simpan catatan' }}</button>
    </form>

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 12px;">Batal</a>
</div>
