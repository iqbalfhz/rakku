<?php

use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetWriter;
use App\Services\LedgerReport;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    public bool $isAdding = false;

    public string $categoryPublicId = '';

    public string $amount = '';

    public bool $alertEnabled = false;

    public string $alertThreshold = '80';

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    public function startAdding(): void
    {
        $this->resetForm();

        $this->categoryPublicId = (string) $this->availableCategories()->value('public_id');
        $this->isAdding = true;
    }

    public function startEditing(int $budgetId): void
    {
        $budget = Budget::query()->visible()->findOrFail($budgetId);

        $this->editingId = $budget->id;
        $this->isAdding = false;
        $this->categoryPublicId = $budget->category_public_id;
        $this->amount = (string) (int) $budget->amount;
        $this->alertEnabled = $budget->alert_enabled;
        $this->alertThreshold = (string) $budget->alertThreshold();
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(BudgetWriter $budgetWriter): void
    {
        $data = $this->validate([
            'categoryPublicId' => ['required', 'exists:categories,public_id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'alertThreshold' => ['required', 'integer', 'min:1', 'max:100'],
        ], attributes: [
            'categoryPublicId' => 'kategori',
            'amount' => 'limit',
            'alertThreshold' => 'ambang peringatan',
        ]);

        $attributes = [
            'category_public_id' => $data['categoryPublicId'],
            'amount' => (float) $data['amount'],
            'alert_enabled' => $this->isPremium() && $this->alertEnabled,
            'alert_threshold_percent' => (int) $data['alertThreshold'],
        ];

        $budget = $this->editingId === null ? null : Budget::query()->find($this->editingId);

        $budget === null
            ? $budgetWriter->record($attributes)
            : $budgetWriter->revise($budget, $attributes);

        $this->resetForm();
    }

    public function remove(int $budgetId, BudgetWriter $budgetWriter): void
    {
        $budget = Budget::query()->visible()->find($budgetId);

        if ($budget !== null) {
            $budgetWriter->remove($budget);
        }

        $this->resetForm();
    }

    /**
     * @return Collection<int, Budget>
     */
    public function budgets(): Collection
    {
        return Budget::query()->visible()->with('category')->get()
            ->sortBy(fn (Budget $budget): string => $budget->category->name ?? '')
            ->values();
    }

    public function spentOn(Budget $budget): float
    {
        return $this->spending()[$budget->category_public_id] ?? 0.0;
    }

    /**
     * Kategori pengeluaran yang belum punya anggaran, ditambah yang sedang diubah.
     *
     * @return Collection<int, Category>
     */
    public function availableCategories(): Collection
    {
        $taken = Budget::query()->visible()
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->pluck('category_public_id');

        return Category::query()
            ->visible()
            ->where('type', 'expense')
            ->whereNotIn('public_id', $taken)
            ->orderBy('name')
            ->get();
    }

    public function monthLabel(): string
    {
        return today()->translatedFormat('F Y');
    }

    /**
     * Total pengeluaran bulan berjalan per kategori, diambil sekali untuk seluruh daftar.
     *
     * @return array<string, float>
     */
    private function spending(): array
    {
        return once(fn (): array => app(LedgerReport::class)
            ->spendingByCategoryId(today()->startOfMonth(), today()->endOfMonth()));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->isAdding = false;
        $this->categoryPublicId = '';
        $this->amount = '';
        $this->alertEnabled = false;
        $this->alertThreshold = '80';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Anggaran</p>
        <h1 class="masthead__title">Jatah bulan<br>{{ $this->monthLabel() }}.</h1>
        <p class="masthead__note">Pemakaian dihitung dari pengeluaran bulan berjalan.</p>
    </header>

    <section class="tape">
        <p class="tape__label">Batas per kategori</p>

        @forelse ($this->budgets() as $budget)
            @php ($spent = $this->spentOn($budget))
            @php ($percent = $budget->usagePercent($spent))
            @php ($remaining = (float) $budget->amount - $spent)

            <div class="entry" style="display: block;">
                <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
                    <div class="entry__label">
                        <p class="entry__title">{{ $budget->category->name ?? 'Kategori terhapus' }}</p>
                        <p class="entry__meta">
                            {{ Rupiah::format($spent) }} dari {{ Rupiah::format((float) $budget->amount) }}
                            @if ($budget->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                        </p>
                    </div>

                    <span class="entry__amount {{ $remaining < 0 ? 'numeral--debit' : '' }}">
                        {{ $remaining < 0 ? '−' : '' }}{{ Rupiah::format(abs($remaining)) }}
                    </span>
                </div>

                <div class="gauge">
                    <span class="gauge__fill {{ $percent >= 100 ? 'gauge__fill--over' : ($percent >= $budget->alertThreshold() ? 'gauge__fill--warn' : '') }}"
                          style="width: {{ min($percent, 100) }}%;"></span>
                </div>

                <p class="entry__meta" style="margin-top: 8px;">
                    Terpakai {{ $percent }}%{{ $remaining < 0 ? ' · lewat batas' : '' }}
                    · <button class="linkish" type="button" wire:click="startEditing({{ $budget->id }})">Ubah</button>
                    · <button class="linkish" type="button" wire:click="remove({{ $budget->id }})"
                              wire:confirm="Hapus anggaran ini?">Hapus</button>
                </p>
            </div>
        @empty
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title muted">Belum ada anggaran</p>
                    <p class="entry__meta">Tentukan jatah bulanan untuk kategori yang sering bocor</p>
                </div>
                <span class="stamp stamp--muted">Kosong</span>
            </div>
        @endforelse

        @unless ($isAdding || $editingId)
            @if ($this->availableCategories()->isNotEmpty())
                <button class="button" type="button" wire:click="startAdding" style="margin-top: 20px;">Tambah anggaran</button>
            @else
                <p class="muted small" style="margin: 20px 0 0;">Semua kategori pengeluaran sudah punya anggaran.</p>
            @endif
        @endunless
    </section>

    @if ($isAdding || $editingId)
        <section class="tape">
            <p class="tape__label">{{ $editingId ? 'Ubah anggaran' : 'Anggaran baru' }}</p>

            <form wire:submit="save">
                <div class="field">
                    <label class="field__label" for="budget-category">Kategori</label>
                    <select class="field__input" id="budget-category" wire:model="categoryPublicId">
                        @foreach ($this->availableCategories() as $category)
                            <option value="{{ $category->public_id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="budget-amount">Limit per bulan</label>
                    <input class="field__input field__input--numeral" id="budget-amount" type="number" inputmode="numeric"
                           min="1" step="1" placeholder="0" wire:model="amount">
                    @error('amount') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                @if ($this->isPremium())
                    <div class="field">
                        <label class="field__label" style="cursor: pointer;">
                            <input type="checkbox" wire:model.live="alertEnabled" style="margin-right: 8px;">Ingatkan saat mendekati batas
                        </label>
                    </div>

                    @if ($alertEnabled)
                        <div class="field">
                            <label class="field__label" for="budget-threshold">Ambang peringatan (%)</label>
                            <input class="field__input field__input--numeral" id="budget-threshold" type="number"
                                   inputmode="numeric" min="1" max="100" step="1" wire:model="alertThreshold">
                            @error('alertThreshold') <p class="field__hint">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @else
                    <p class="muted small">Peringatan otomatis saat mendekati batas tersedia setelah langganan aktif.</p>
                @endif

                <button class="button" type="submit">Simpan anggaran</button>
            </form>

            <button class="button button--quiet" type="button" wire:click="cancel" style="margin-top: 10px;">Batal</button>
        </section>
    @endif

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
