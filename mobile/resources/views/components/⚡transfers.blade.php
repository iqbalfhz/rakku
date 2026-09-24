<?php

use App\Models\Account;
use App\Models\Transfer;
use App\Services\TransferWriter;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * Berapa banyak pemindahan terakhir yang ditampilkan.
     */
    private const int RECENT_LIMIT = 20;

    public ?int $editingId = null;

    public bool $isAdding = false;

    public string $fromAccountPublicId = '';

    public string $toAccountPublicId = '';

    public string $amount = '';

    public string $description = '';

    public string $transferDate = '';

    public ?string $error = null;

    public function startAdding(): void
    {
        $this->resetForm();

        $accounts = $this->accounts();

        $this->fromAccountPublicId = (string) $accounts->first()?->public_id;
        $this->toAccountPublicId = (string) $accounts->skip(1)->first()?->public_id;
        $this->transferDate = today()->toDateString();
        $this->isAdding = true;
    }

    public function startEditing(int $transferId): void
    {
        $transfer = Transfer::query()->visible()->findOrFail($transferId);

        $this->resetForm();

        $this->editingId = $transfer->id;
        $this->fromAccountPublicId = $transfer->from_account_public_id;
        $this->toAccountPublicId = $transfer->to_account_public_id;
        $this->amount = (string) (int) $transfer->amount;
        $this->description = (string) $transfer->description;
        $this->transferDate = $transfer->transfer_date->toDateString();
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(TransferWriter $transferWriter): void
    {
        $data = $this->validate([
            'fromAccountPublicId' => ['required', 'exists:accounts,public_id'],
            'toAccountPublicId' => ['required', 'exists:accounts,public_id', 'different:fromAccountPublicId'],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'transferDate' => ['required', 'date'],
        ], attributes: [
            'fromAccountPublicId' => 'akun asal',
            'toAccountPublicId' => 'akun tujuan',
            'amount' => 'nominal',
            'description' => 'keterangan',
            'transferDate' => 'tanggal',
        ], messages: [
            'toAccountPublicId.different' => 'Akun tujuan harus berbeda dari akun asal.',
        ]);

        $attributes = [
            'from_account_public_id' => $data['fromAccountPublicId'],
            'to_account_public_id' => $data['toAccountPublicId'],
            'amount' => (float) $data['amount'],
            'description' => $data['description'] ?: null,
            'transfer_date' => $data['transferDate'],
        ];

        $transfer = $this->editingId === null ? null : Transfer::query()->find($this->editingId);

        $transfer === null
            ? $transferWriter->record($attributes)
            : $transferWriter->revise($transfer, $attributes);

        $this->resetForm();
    }

    public function remove(int $transferId, TransferWriter $transferWriter): void
    {
        $transfer = Transfer::query()->visible()->find($transferId);

        if ($transfer !== null) {
            $transferWriter->remove($transfer);
        }

        $this->resetForm();
    }

    /**
     * @return Collection<int, Transfer>
     */
    public function transfers(): Collection
    {
        return Transfer::query()
            ->visible()
            ->with(['fromAccount', 'toAccount'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    public function hasEnoughAccounts(): bool
    {
        return $this->accounts()->count() >= 2;
    }

    private function resetForm(): void
    {
        $this->error = null;
        $this->editingId = null;
        $this->isAdding = false;
        $this->fromAccountPublicId = '';
        $this->toAccountPublicId = '';
        $this->amount = '';
        $this->description = '';
        $this->transferDate = '';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Pindah uang</p>
        <h1 class="masthead__title">Dari laci<br>ke rekening.</h1>
        <p class="masthead__note">Bukan pemasukan, bukan pengeluaran — uangnya cuma berpindah tempat.</p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    <section class="tape">
        <p class="tape__label">Pemindahan terakhir</p>

        @forelse ($this->transfers() as $transfer)
            <div class="entry" style="display: block;">
                <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
                    <div class="entry__label">
                        <p class="entry__title">{{ $transfer->routeLabel() }}</p>
                        <p class="entry__meta">
                            {{ $transfer->transfer_date->translatedFormat('j M Y') }}
                            @if ($transfer->description) · {{ $transfer->description }} @endif
                            @if ($transfer->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                        </p>
                    </div>

                    <span class="entry__amount">{{ Rupiah::format((float) $transfer->amount) }}</span>
                </div>

                <p class="entry__meta" style="margin-top: 8px;">
                    <button class="linkish" type="button" wire:click="startEditing({{ $transfer->id }})">Ubah</button>
                    · <button class="linkish" type="button" wire:click="remove({{ $transfer->id }})"
                              wire:confirm="Batalkan pemindahan ini? Saldo kedua akun dikembalikan.">Batalkan</button>
                </p>
            </div>
        @empty
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title muted">Belum ada pemindahan</p>
                    <p class="entry__meta">Setor tunai ke bank, tarik dari e-wallet, dan sejenisnya</p>
                </div>
                <span class="stamp stamp--muted">Kosong</span>
            </div>
        @endforelse

        @unless ($isAdding || $editingId)
            @if ($this->hasEnoughAccounts())
                <button class="button" type="button" wire:click="startAdding" style="margin-top: 20px;">Pindahkan uang</button>
            @else
                <p class="muted small" style="margin: 20px 0 0;">
                    Perlu sedikitnya dua akun untuk memindahkan uang.
                </p>
                <a class="button button--quiet" href="{{ route('setup') }}" wire:navigate style="margin-top: 12px;">Tambah akun</a>
            @endif
        @endunless
    </section>

    @if ($isAdding || $editingId)
        <section class="tape">
            <p class="tape__label">{{ $editingId ? 'Ubah pemindahan' : 'Pindahkan uang' }}</p>

            <form wire:submit="save">
                <div class="field">
                    <label class="field__label" for="from-account">Dari akun</label>
                    <select class="field__input" id="from-account" wire:model="fromAccountPublicId">
                        @foreach ($this->accounts() as $account)
                            <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('fromAccountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="to-account">Ke akun</label>
                    <select class="field__input" id="to-account" wire:model="toAccountPublicId">
                        @foreach ($this->accounts() as $account)
                            <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('toAccountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="transfer-amount">Nominal</label>
                    <input class="field__input field__input--numeral" id="transfer-amount" type="number"
                           inputmode="numeric" min="1" step="1" placeholder="0" wire:model="amount">
                    @error('amount') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="transfer-date">Tanggal</label>
                    <input class="field__input" id="transfer-date" type="date" wire:model="transferDate">
                    @error('transferDate') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="transfer-description">Keterangan</label>
                    <input class="field__input" id="transfer-description" type="text" placeholder="Setor hasil jualan"
                           wire:model="description">
                    @error('description') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <button class="button" type="submit">Simpan pemindahan</button>
            </form>

            <button class="button button--quiet" type="button" wire:click="cancel" style="margin-top: 10px;">Batal</button>
        </section>
    @endif

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
