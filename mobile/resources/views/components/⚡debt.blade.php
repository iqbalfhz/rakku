<?php

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\DebtWriter;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public ?Debt $debt = null;

    public string $type = 'receivable';

    public string $counterpartyName = '';

    public string $amount = '';

    public string $dueDate = '';

    public string $description = '';

    public bool $reminderEnabled = true;

    public string $paymentAmount = '';

    public string $paymentAccountPublicId = '';

    public string $paymentDate = '';

    public string $paymentNotes = '';

    public bool $isEditing = false;

    public ?string $error = null;

    public function mount(?string $publicId = null): void
    {
        if (! app(PlanGate::class)->isPremium()) {
            $this->redirect(route('debts'));

            return;
        }

        $this->paymentDate = today()->toDateString();
        $this->paymentAccountPublicId = (string) Account::query()->visible()->value('public_id');

        if ($publicId === null) {
            $this->isEditing = true;

            return;
        }

        $this->debt = Debt::query()->visible()->where('public_id', $publicId)->firstOrFail();

        $this->fillFormFromDebt();
    }

    public function isNew(): bool
    {
        return $this->debt === null;
    }

    public function startEditing(): void
    {
        $this->fillFormFromDebt();

        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->resetValidation();

        $this->isEditing = false;
    }

    /**
     * Simpan utang ke ponsel. Pengiriman ke server menyusul saat ada sinyal.
     */
    public function save(DebtWriter $debtWriter): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:receivable,payable'],
            'counterpartyName' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'dueDate' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'counterpartyName' => 'nama',
            'amount' => 'nominal',
            'dueDate' => 'jatuh tempo',
            'description' => 'keterangan',
        ]);

        $attributes = [
            'type' => $data['type'],
            'counterparty_name' => $data['counterpartyName'],
            'amount' => (float) $data['amount'],
            'due_date' => $data['dueDate'] ?: null,
            'description' => $data['description'] ?: null,
            'reminder_enabled' => $this->reminderEnabled,
        ];

        if ($this->debt === null) {
            $this->redirect(route('debt.show', $debtWriter->record($attributes)->public_id));

            return;
        }

        $debtWriter->revise($this->debt, $attributes);

        $this->debt->refresh();
        $this->isEditing = false;
    }

    /**
     * Catat cicilan. Saldo akun ikut berubah seketika lewat transaksi yang dibuat bersamanya.
     */
    public function pay(DebtWriter $debtWriter): void
    {
        $data = $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:1', 'max:'.(float) $this->debt->remaining_amount],
            'paymentAccountPublicId' => ['required', 'exists:accounts,public_id'],
            'paymentDate' => ['required', 'date'],
            'paymentNotes' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'paymentAmount' => 'nominal cicilan',
            'paymentAccountPublicId' => 'akun',
            'paymentDate' => 'tanggal',
            'paymentNotes' => 'catatan',
        ]);

        $debtWriter->pay($this->debt, [
            'account_public_id' => $data['paymentAccountPublicId'],
            'amount' => (float) $data['paymentAmount'],
            'payment_date' => $data['paymentDate'],
            'notes' => $data['paymentNotes'] ?: null,
        ]);

        $this->debt->refresh();
        $this->paymentAmount = '';
        $this->paymentNotes = '';
    }

    public function remove(DebtWriter $debtWriter): void
    {
        if (! $debtWriter->remove($this->debt)) {
            $this->error = 'Utang yang sudah dicicil tidak bisa dihapus. Hapus dulu cicilannya lewat aplikasi web.';

            return;
        }

        $this->redirect(route('debts'));
    }

    public function paidAmount(): float
    {
        return (float) $this->debt->amount - (float) $this->debt->remaining_amount;
    }

    /**
     * @return Collection<int, DebtPayment>
     */
    public function payments(): Collection
    {
        return $this->debt->payments()->orderByDesc('payment_date')->orderByDesc('id')->get();
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    private function fillFormFromDebt(): void
    {
        $this->type = $this->debt->type;
        $this->counterpartyName = $this->debt->counterparty_name;
        $this->amount = (string) (int) $this->debt->amount;
        $this->dueDate = (string) $this->debt->due_date?->toDateString();
        $this->description = (string) $this->debt->description;
        $this->reminderEnabled = $this->debt->reminder_enabled;
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">{{ $this->isNew() ? 'Catatan baru' : $debt->typeLabel() }}</p>
        <h1 class="masthead__title">
            @if ($this->isNew())
                Utang atau<br>piutang?
            @else
                {{ $debt->counterparty_name }}
            @endif
        </h1>
        <p class="masthead__note">
            {{ $this->isNew() ? 'Catat siapa yang berutang dan berapa nilainya.' : $debt->summaryLabel() }}
        </p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    @if (! $this->isNew())
        <section class="tape">
            <p class="tape__label">Sisa yang belum lunas</p>
            <p class="numeral {{ $debt->isReceivable() ? 'numeral--credit' : 'numeral--debit' }}">
                {{ Rupiah::format((float) $debt->remaining_amount) }}
            </p>

            <div class="entry" style="margin-top: 16px;">
                <div class="entry__label">
                    <p class="entry__title">Nilai awal</p>
                    <p class="entry__meta">
                        @if ($debt->due_date)
                            Jatuh tempo {{ $debt->due_date->translatedFormat('j M Y') }}
                        @else
                            Tanpa jatuh tempo
                        @endif
                        @if ($debt->isOverdue()) · <span class="stamp stamp--due">Lewat</span> @endif
                    </p>
                </div>
                <span class="entry__amount">{{ Rupiah::format((float) $debt->amount) }}</span>
            </div>

            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title">Sudah dibayar</p>
                    @if ($debt->description)
                        <p class="entry__meta">{{ $debt->description }}</p>
                    @endif
                </div>
                <span class="entry__amount">{{ Rupiah::format($this->paidAmount()) }}</span>
            </div>

            @if ($debt->isPaid())
                <p style="margin: 16px 0 0;"><span class="stamp">Lunas</span></p>
            @endif
        </section>
    @endif

    @if (! $this->isNew() && ! $debt->isPaid())
        <section class="tape">
            <p class="tape__label">Catat cicilan</p>

            <form wire:submit="pay">
                <div class="field">
                    <label class="field__label" for="payment-amount">Nominal</label>
                    <input class="field__input field__input--numeral" id="payment-amount" type="number" inputmode="numeric"
                           min="1" step="1" placeholder="0" wire:model="paymentAmount">
                    @error('paymentAmount') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="payment-account">Lewat akun</label>
                    <select class="field__input" id="payment-account" wire:model="paymentAccountPublicId">
                        @foreach ($this->accounts() as $account)
                            <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('paymentAccountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="payment-date">Tanggal</label>
                    <input class="field__input" id="payment-date" type="date" wire:model="paymentDate">
                    @error('paymentDate') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="payment-notes">Catatan</label>
                    <input class="field__input" id="payment-notes" type="text" placeholder="Bayar tunai di warung"
                           wire:model="paymentNotes">
                    @error('paymentNotes') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <button class="button" type="submit">Simpan cicilan</button>
            </form>
        </section>
    @endif

    @if (! $this->isNew())
        <section class="tape">
            <p class="tape__label">Riwayat cicilan</p>

            @forelse ($this->payments() as $payment)
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title">{{ $payment->notes ?: 'Tanpa catatan' }}</p>
                        <p class="entry__meta">
                            {{ $payment->payment_date->translatedFormat('j M Y') }}
                            @if ($payment->account) · {{ $payment->account->name }} @endif
                            @if ($payment->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                        </p>
                    </div>
                    <span class="entry__amount">{{ Rupiah::format((float) $payment->amount) }}</span>
                </div>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada cicilan</p>
                        <p class="entry__meta">Setiap cicilan ikut tercatat sebagai transaksi</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse
        </section>
    @endif

    @if ($isEditing)
        <section class="tape">
            <p class="tape__label">{{ $this->isNew() ? 'Rincian' : 'Ubah rincian' }}</p>

            <form wire:submit="save">
                <div class="field">
                    <span class="field__label">Jenis</span>
                    <div class="choice">
                        <label class="choice__option {{ $type === 'receivable' ? 'choice__option--on' : '' }}">
                            <input type="radio" value="receivable" wire:model.live="type"> Piutang
                        </label>
                        <label class="choice__option {{ $type === 'payable' ? 'choice__option--on' : '' }}">
                            <input type="radio" value="payable" wire:model.live="type"> Utang
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="counterparty">{{ $type === 'receivable' ? 'Yang berutang' : 'Yang dibayar' }}</label>
                    <input class="field__input" id="counterparty" type="text" placeholder="Bu Rina"
                           wire:model="counterpartyName">
                    @error('counterpartyName') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="amount">Nominal</label>
                    <input class="field__input field__input--numeral" id="amount" type="number" inputmode="numeric"
                           min="1" step="1" placeholder="0" wire:model="amount">
                    @error('amount') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="due-date">Jatuh tempo</label>
                    <input class="field__input" id="due-date" type="date" wire:model="dueDate">
                    @error('dueDate') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="debt-description">Keterangan</label>
                    <input class="field__input" id="debt-description" type="text" placeholder="Ambil beras 10 kg"
                           wire:model="description">
                    @error('description') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" style="cursor: pointer;">
                        <input type="checkbox" wire:model="reminderEnabled" style="margin-right: 8px;">Ingatkan saat jatuh tempo
                    </label>
                </div>

                <button class="button" type="submit">{{ $this->isNew() ? 'Simpan catatan' : 'Simpan perubahan' }}</button>
            </form>

            @unless ($this->isNew())
                <button class="button button--quiet" type="button" wire:click="cancelEditing" style="margin-top: 10px;">Batal ubah</button>
            @endunless
        </section>
    @elseif (! $this->isNew())
        <button class="button button--quiet" type="button" wire:click="startEditing">Ubah rincian</button>

        <button class="button button--quiet" type="button" wire:click="remove" wire:confirm="Hapus catatan utang ini?"
                style="margin-top: 10px;">Hapus utang</button>
    @endif

    <a class="button button--quiet" href="{{ route('debts') }}" wire:navigate style="margin-top: 10px;">Kembali</a>
</div>
