<?php

use App\Models\Account;
use App\Models\Category;
use App\Services\LedgerWriter;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $type = 'expense';

    public string $amount = '';

    public string $accountPublicId = '';

    public string $categoryPublicId = '';

    public string $description = '';

    public string $transactionDate = '';

    public function mount(): void
    {
        $this->transactionDate = today()->toDateString();
        $this->accountPublicId = (string) Account::query()->value('public_id');
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

        $ledgerWriter->record([
            'account_public_id' => $data['accountPublicId'],
            'category_public_id' => $data['categoryPublicId'] ?: null,
            'type' => $data['type'],
            'amount' => (float) $data['amount'],
            'description' => $data['description'] ?: null,
            'transaction_date' => $data['transactionDate'],
        ]);

        $this->redirect(route('home'));
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->orderBy('name')->get();
    }

    /**
     * Kategori mengikuti jenis transaksi yang sedang dipilih.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->where('type', $this->type)->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        $this->categoryPublicId = '';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Catatan baru</p>
        <h1 class="masthead__title">Tulis<br>transaksi.</h1>
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

        <button class="button" type="submit">Simpan catatan</button>
    </form>

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 12px;">Batal</a>
</div>
