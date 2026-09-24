<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Services\PlanGate;
use App\Services\RecurringWriter;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    public bool $isAdding = false;

    public string $type = 'expense';

    public string $accountPublicId = '';

    public string $categoryPublicId = '';

    public string $amount = '';

    public string $description = '';

    public string $frequency = 'monthly';

    public string $startDate = '';

    public string $endDate = '';

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    public function startAdding(): void
    {
        $this->resetForm();

        $this->accountPublicId = (string) Account::query()->visible()->value('public_id');
        $this->startDate = today()->toDateString();
        $this->isAdding = true;
    }

    public function startEditing(int $recurringId): void
    {
        $recurring = RecurringTransaction::query()->visible()->findOrFail($recurringId);

        $this->editingId = $recurring->id;
        $this->isAdding = false;
        $this->type = $recurring->type;
        $this->accountPublicId = $recurring->account_public_id;
        $this->categoryPublicId = (string) $recurring->category_public_id;
        $this->amount = (string) (int) $recurring->amount;
        $this->description = (string) $recurring->description;
        $this->frequency = $recurring->frequency;
        $this->startDate = $recurring->start_date->toDateString();
        $this->endDate = (string) $recurring->end_date?->toDateString();
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(RecurringWriter $recurringWriter): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:income,expense'],
            'accountPublicId' => ['required', 'exists:accounts,public_id'],
            'categoryPublicId' => ['nullable', 'exists:categories,public_id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ], attributes: [
            'accountPublicId' => 'akun',
            'categoryPublicId' => 'kategori',
            'amount' => 'nominal',
            'description' => 'keterangan',
            'frequency' => 'pengulangan',
            'startDate' => 'mulai',
            'endDate' => 'berakhir',
        ]);

        $attributes = [
            'account_public_id' => $data['accountPublicId'],
            'category_public_id' => $data['categoryPublicId'] ?: null,
            'type' => $data['type'],
            'amount' => (float) $data['amount'],
            'description' => $data['description'] ?: null,
            'frequency' => $data['frequency'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'] ?: null,
        ];

        $recurring = $this->editingId === null ? null : RecurringTransaction::query()->find($this->editingId);

        $recurring === null
            ? $recurringWriter->record($attributes)
            : $recurringWriter->revise($recurring, $attributes);

        $this->resetForm();
    }

    public function togglePause(int $recurringId, RecurringWriter $recurringWriter): void
    {
        $recurring = RecurringTransaction::query()->visible()->find($recurringId);

        if ($recurring !== null) {
            $recurringWriter->togglePause($recurring);
        }
    }

    public function remove(int $recurringId, RecurringWriter $recurringWriter): void
    {
        $recurring = RecurringTransaction::query()->visible()->find($recurringId);

        if ($recurring !== null) {
            $recurringWriter->remove($recurring);
        }

        $this->resetForm();
    }

    /**
     * @return Collection<int, RecurringTransaction>
     */
    public function schedules(): Collection
    {
        return RecurringTransaction::query()
            ->visible()
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->get();
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
        return Category::query()->visible()->where('type', $this->type)->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        $this->categoryPublicId = '';
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->isAdding = false;
        $this->type = 'expense';
        $this->accountPublicId = '';
        $this->categoryPublicId = '';
        $this->amount = '';
        $this->description = '';
        $this->frequency = 'monthly';
        $this->startDate = '';
        $this->endDate = '';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Transaksi berulang</p>
        <h1 class="masthead__title">Yang datang<br>tiap bulan.</h1>
        <p class="masthead__note">Diatur dari sini, dicatat sendiri oleh server tiap jadwalnya tiba.</p>
    </header>

    @if (! $this->isPremium())
        <section class="tape">
            <p class="tape__label">Fitur premium</p>
            <p class="entry__title" style="margin: 8px 0 0;">Transaksi berulang terbuka setelah langganan aktif.</p>
            <a class="button" href="{{ route('subscription') }}" wire:navigate style="margin-top: 18px;">Aktifkan premium</a>
        </section>

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 16px;">Kembali</a>
    @else
        <section class="tape">
            <p class="tape__label">Jadwal</p>

            @forelse ($this->schedules() as $schedule)
                <div class="entry" style="display: block;">
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
                        <div class="entry__label">
                            <p class="entry__title">{{ $schedule->description ?: 'Tanpa keterangan' }}</p>
                            <p class="entry__meta">
                                {{ $schedule->frequencyLabel() }} · {{ $schedule->nextRunLabel() }}
                                @if ($schedule->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                            </p>
                        </div>

                        <span class="entry__amount {{ $schedule->isIncome() ? 'numeral--credit' : 'numeral--debit' }}">
                            {{ $schedule->isIncome() ? '+' : '−' }}{{ Rupiah::format((float) $schedule->amount) }}
                        </span>
                    </div>

                    <p class="entry__meta" style="margin-top: 8px;">
                        <button class="linkish" type="button" wire:click="togglePause({{ $schedule->id }})">
                            {{ $schedule->is_active ? 'Jeda' : 'Jalankan lagi' }}
                        </button>
                        · <button class="linkish" type="button" wire:click="startEditing({{ $schedule->id }})">Ubah</button>
                        · <button class="linkish" type="button" wire:click="remove({{ $schedule->id }})"
                                  wire:confirm="Hapus jadwal ini? Transaksi yang sudah terlanjur dibuat tidak ikut terhapus.">Hapus</button>
                    </p>
                </div>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada jadwal</p>
                        <p class="entry__meta">Sewa, listrik, gaji — yang tiap bulan angkanya sama</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse

            @unless ($isAdding || $editingId)
                <button class="button" type="button" wire:click="startAdding" style="margin-top: 20px;">Tambah jadwal</button>
            @endunless
        </section>

        @if ($isAdding || $editingId)
            <section class="tape">
                <p class="tape__label">{{ $editingId ? 'Ubah jadwal' : 'Jadwal baru' }}</p>

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
                        <label class="field__label" for="recurring-amount">Nominal</label>
                        <input class="field__input field__input--numeral" id="recurring-amount" type="number"
                               inputmode="numeric" min="1" step="1" placeholder="0" wire:model="amount">
                        @error('amount') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-description">Keterangan</label>
                        <input class="field__input" id="recurring-description" type="text" placeholder="Bayar listrik"
                               wire:model="description">
                        @error('description') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-account">Akun</label>
                        <select class="field__input" id="recurring-account" wire:model="accountPublicId">
                            @foreach ($this->accounts() as $account)
                                <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('accountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-category">Kategori</label>
                        <select class="field__input" id="recurring-category" wire:model="categoryPublicId">
                            <option value="">Tanpa kategori</option>
                            @foreach ($this->categories() as $category)
                                <option value="{{ $category->public_id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-frequency">Berulang</label>
                        <select class="field__input" id="recurring-frequency" wire:model="frequency">
                            <option value="daily">Harian</option>
                            <option value="weekly">Mingguan</option>
                            <option value="monthly">Bulanan</option>
                            <option value="yearly">Tahunan</option>
                        </select>
                        @error('frequency') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-start">Mulai</label>
                        <input class="field__input" id="recurring-start" type="date" wire:model="startDate">
                        @error('startDate') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="recurring-end">Berakhir</label>
                        <input class="field__input" id="recurring-end" type="date" wire:model="endDate">
                        @error('endDate') <p class="field__hint">{{ $message }}</p> @enderror
                        <p class="field__hint">Kosongkan kalau berjalan terus.</p>
                    </div>

                    <button class="button" type="submit">Simpan jadwal</button>
                </form>

                <button class="button button--quiet" type="button" wire:click="cancel" style="margin-top: 10px;">Batal</button>
            </section>
        @endif

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
    @endif
</div>
