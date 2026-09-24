<?php

use App\Models\Account;
use App\Models\Category;
use App\Services\LedgerSetupWriter;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'accounts';

    public ?int $editingAccountId = null;

    public bool $isAddingAccount = false;

    public string $accountName = '';

    public string $accountType = 'cash';

    public string $initialBalance = '';

    public ?int $editingCategoryId = null;

    public bool $isAddingCategory = false;

    public string $categoryName = '';

    public string $categoryType = 'expense';

    public ?string $error = null;

    public function startAddingAccount(): void
    {
        $this->resetForms();

        $this->isAddingAccount = true;
        $this->initialBalance = '0';
    }

    public function startEditingAccount(int $accountId): void
    {
        $account = Account::query()->visible()->findOrFail($accountId);

        $this->resetForms();

        $this->editingAccountId = $account->id;
        $this->accountName = $account->name;
        $this->accountType = $account->type;
        $this->initialBalance = (string) (int) $account->initial_balance;
    }

    public function saveAccount(LedgerSetupWriter $writer): void
    {
        $data = $this->validate([
            'accountName' => ['required', 'string', 'max:255'],
            'accountType' => ['required', 'in:cash,bank,e-wallet,other'],
            'initialBalance' => ['required', 'numeric'],
        ], attributes: [
            'accountName' => 'nama akun',
            'accountType' => 'jenis akun',
            'initialBalance' => 'saldo awal',
        ]);

        $attributes = [
            'name' => $data['accountName'],
            'type' => $data['accountType'],
            'initial_balance' => (float) $data['initialBalance'],
        ];

        $account = $this->editingAccountId === null ? null : Account::query()->find($this->editingAccountId);

        $account === null
            ? $writer->recordAccount($attributes)
            : $writer->reviseAccount($account, $attributes);

        $this->resetForms();
    }

    public function removeAccount(int $accountId, LedgerSetupWriter $writer): void
    {
        $account = Account::query()->visible()->find($accountId);

        if ($account !== null && ! $writer->removeAccount($account)) {
            $this->error = 'Akun yang sudah dipakai mencatat tidak bisa dihapus. Saldo catatan lain ikut bergantung padanya.';

            return;
        }

        $this->resetForms();
    }

    public function startAddingCategory(): void
    {
        $this->resetForms();

        $this->isAddingCategory = true;
    }

    public function startEditingCategory(int $categoryId): void
    {
        $category = Category::query()->visible()->findOrFail($categoryId);

        $this->resetForms();

        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryType = $category->type;
    }

    public function saveCategory(LedgerSetupWriter $writer): void
    {
        $data = $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
            'categoryType' => ['required', 'in:income,expense'],
        ], attributes: ['categoryName' => 'nama kategori', 'categoryType' => 'jenis']);

        $attributes = ['name' => $data['categoryName'], 'type' => $data['categoryType']];

        $category = $this->editingCategoryId === null ? null : Category::query()->find($this->editingCategoryId);

        $category === null
            ? $writer->recordCategory($attributes)
            : $writer->reviseCategory($category, $attributes);

        $this->resetForms();
    }

    public function removeCategory(int $categoryId, LedgerSetupWriter $writer): void
    {
        $category = Category::query()->visible()->find($categoryId);

        if ($category !== null) {
            $writer->removeCategory($category);
        }

        $this->resetForms();
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForms();
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->visible()->orderBy('type')->orderBy('name')->get();
    }

    private function resetForms(): void
    {
        $this->error = null;
        $this->editingAccountId = null;
        $this->isAddingAccount = false;
        $this->accountName = '';
        $this->accountType = 'cash';
        $this->initialBalance = '';
        $this->editingCategoryId = null;
        $this->isAddingCategory = false;
        $this->categoryName = '';
        $this->categoryType = 'expense';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Pengaturan buku</p>
        <h1 class="masthead__title">Akun dan<br>kategori.</h1>
        <p class="masthead__note">Rangka buku kas: tempat uang disimpan dan cara mengelompokkannya.</p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    <div class="field">
        <div class="choice">
            <label class="choice__option {{ $tab === 'accounts' ? 'choice__option--on' : '' }}">
                <input type="radio" value="accounts" wire:model.live="tab"> Akun
            </label>
            <label class="choice__option {{ $tab === 'categories' ? 'choice__option--on' : '' }}">
                <input type="radio" value="categories" wire:model.live="tab"> Kategori
            </label>
        </div>
    </div>

    @if ($tab === 'accounts')
        <section class="tape">
            <p class="tape__label">Akun</p>

            @forelse ($this->accounts() as $account)
                <div class="entry" style="display: block;">
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
                        <div class="entry__label">
                            <p class="entry__title">{{ $account->name }}</p>
                            <p class="entry__meta">
                                {{ $account->typeLabel() }} · awal {{ Rupiah::format((float) $account->initial_balance) }}
                                @if ($account->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                            </p>
                        </div>

                        <span class="entry__amount">{{ Rupiah::format((float) $account->current_balance) }}</span>
                    </div>

                    <p class="entry__meta" style="margin-top: 8px;">
                        <button class="linkish" type="button" wire:click="startEditingAccount({{ $account->id }})">Ubah</button>
                        @if ($account->canBeRemoved())
                            · <button class="linkish" type="button" wire:click="removeAccount({{ $account->id }})"
                                      wire:confirm="Hapus akun ini?">Hapus</button>
                        @else
                            · <span class="muted">sudah dipakai, tidak bisa dihapus</span>
                        @endif
                    </p>
                </div>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada akun</p>
                        <p class="entry__meta">Kas laci, rekening bank, dompet digital</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse

            @unless ($isAddingAccount || $editingAccountId)
                <button class="button" type="button" wire:click="startAddingAccount" style="margin-top: 20px;">Tambah akun</button>
            @endunless
        </section>

        @if ($isAddingAccount || $editingAccountId)
            <section class="tape">
                <p class="tape__label">{{ $editingAccountId ? 'Ubah akun' : 'Akun baru' }}</p>

                <form wire:submit="saveAccount">
                    <div class="field">
                        <label class="field__label" for="account-name">Nama</label>
                        <input class="field__input" id="account-name" type="text" placeholder="Kas Laci"
                               wire:model="accountName">
                        @error('accountName') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="account-type">Jenis</label>
                        <select class="field__input" id="account-type" wire:model="accountType">
                            <option value="cash">Tunai</option>
                            <option value="bank">Bank</option>
                            <option value="e-wallet">E-Wallet</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('accountType') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="initial-balance">Saldo awal</label>
                        <input class="field__input field__input--numeral" id="initial-balance" type="number"
                               inputmode="numeric" step="1" placeholder="0" wire:model="initialBalance">
                        @error('initialBalance') <p class="field__hint">{{ $message }}</p> @enderror
                        <p class="field__hint">Isi uang yang sudah ada di akun ini sekarang.</p>
                    </div>

                    <button class="button" type="submit">Simpan akun</button>
                </form>

                <button class="button button--quiet" type="button" wire:click="cancel" style="margin-top: 10px;">Batal</button>
            </section>
        @endif
    @else
        <section class="tape">
            <p class="tape__label">Kategori</p>

            @forelse ($this->categories() as $category)
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title">{{ $category->name }}</p>
                        <p class="entry__meta">
                            {{ $category->typeLabel() }}
                            @if ($category->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                            · <button class="linkish" type="button" wire:click="startEditingCategory({{ $category->id }})">Ubah</button>
                            · <button class="linkish" type="button" wire:click="removeCategory({{ $category->id }})"
                                      wire:confirm="Hapus kategori ini? Catatan lama menjadi tanpa kategori.">Hapus</button>
                        </p>
                    </div>

                    <span class="stamp {{ $category->isIncome() ? '' : 'stamp--muted' }}">
                        {{ $category->isIncome() ? 'Masuk' : 'Keluar' }}
                    </span>
                </div>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada kategori</p>
                        <p class="entry__meta">Bensin, bahan baku, gaji — apa pun yang sering berulang</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse

            @unless ($isAddingCategory || $editingCategoryId)
                <button class="button" type="button" wire:click="startAddingCategory" style="margin-top: 20px;">Tambah kategori</button>
            @endunless
        </section>

        @if ($isAddingCategory || $editingCategoryId)
            <section class="tape">
                <p class="tape__label">{{ $editingCategoryId ? 'Ubah kategori' : 'Kategori baru' }}</p>

                <form wire:submit="saveCategory">
                    <div class="field">
                        <label class="field__label" for="category-name">Nama</label>
                        <input class="field__input" id="category-name" type="text" placeholder="Bensin"
                               wire:model="categoryName">
                        @error('categoryName') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <span class="field__label">Jenis</span>
                        <div class="choice">
                            <label class="choice__option {{ $categoryType === 'expense' ? 'choice__option--on' : '' }}">
                                <input type="radio" value="expense" wire:model.live="categoryType"> Pengeluaran
                            </label>
                            <label class="choice__option {{ $categoryType === 'income' ? 'choice__option--on' : '' }}">
                                <input type="radio" value="income" wire:model.live="categoryType"> Pemasukan
                            </label>
                        </div>
                        @error('categoryType') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <button class="button" type="submit">Simpan kategori</button>
                </form>

                <button class="button button--quiet" type="button" wire:click="cancel" style="margin-top: 10px;">Batal</button>
            </section>
        @endif
    @endif

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
