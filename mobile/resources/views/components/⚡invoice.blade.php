<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\ApiClient;
use App\Services\InvoiceWriter;
use App\Services\PlanGate;
use App\Services\TokenStore;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Native\Mobile\Facades\Share;

new class extends Component
{
    public ?Invoice $invoice = null;

    public string $clientPublicId = '';

    public string $issueDate = '';

    public string $dueDate = '';

    public string $notes = '';

    /**
     * @var list<array{description: string, quantity: string, unit_price: string}>
     */
    public array $items = [];

    public string $paymentAccountPublicId = '';

    public string $paidAt = '';

    public bool $isEditing = false;

    public bool $isAddingClient = false;

    public string $newClientName = '';

    public string $newClientPhone = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(?string $publicId = null): void
    {
        if (! app(PlanGate::class)->isPremium()) {
            $this->redirect(route('invoices'));

            return;
        }

        $this->paidAt = today()->toDateString();
        $this->paymentAccountPublicId = (string) Account::query()->visible()->value('public_id');

        if ($publicId === null) {
            $this->issueDate = today()->toDateString();
            $this->dueDate = today()->addDays(14)->toDateString();
            $this->clientPublicId = (string) Client::query()->orderBy('name')->value('public_id');
            $this->items = [$this->blankItem()];
            $this->isEditing = true;

            return;
        }

        $this->invoice = Invoice::query()->visible()->where('public_id', $publicId)->firstOrFail();

        $this->fillFormFromInvoice();
    }

    public function isNew(): bool
    {
        return $this->invoice === null;
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function startEditing(): void
    {
        $this->fillFormFromInvoice();

        $this->isEditing = true;
    }

    /**
     * Klien baru sering muncul justru di lapangan, saat invoice pertamanya dibuat.
     * Nomornya dipakai lagi nanti untuk mengirim tagihan lewat WhatsApp.
     */
    public function addClient(): void
    {
        $data = $this->validate([
            'newClientName' => ['required', 'string', 'max:255'],
            'newClientPhone' => ['nullable', 'string', 'max:30'],
        ], attributes: ['newClientName' => 'nama klien', 'newClientPhone' => 'nomor WhatsApp']);

        $client = Client::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            'name' => $data['newClientName'],
            'phone' => $data['newClientPhone'] ?: null,
            'is_dirty' => true,
        ]);

        $this->clientPublicId = $client->public_id;
        $this->newClientName = '';
        $this->newClientPhone = '';
        $this->isAddingClient = false;
    }

    public function cancelEditing(): void
    {
        $this->resetValidation();

        $this->isEditing = false;
    }

    public function save(InvoiceWriter $invoiceWriter): void
    {
        $data = $this->validate([
            'clientPublicId' => ['required', 'exists:clients,public_id'],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:issueDate'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], attributes: [
            'clientPublicId' => 'klien',
            'issueDate' => 'tanggal terbit',
            'dueDate' => 'jatuh tempo',
            'notes' => 'catatan',
            'items.*.description' => 'keterangan baris',
            'items.*.quantity' => 'jumlah',
            'items.*.unit_price' => 'harga satuan',
        ]);

        $attributes = [
            'client_public_id' => $data['clientPublicId'],
            'issue_date' => $data['issueDate'],
            'due_date' => $data['dueDate'],
            'notes' => $data['notes'] ?: null,
            'items' => array_map(fn (array $item): array => [
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
            ], $data['items']),
        ];

        if ($this->invoice === null) {
            $this->redirect(route('invoice.show', $invoiceWriter->record($attributes)->public_id));

            return;
        }

        $invoiceWriter->revise($this->invoice, $attributes);

        $this->invoice->refresh();
        $this->isEditing = false;
    }

    /**
     * Berbagi selalu butuh sinyal: link PDF-nya dibuat server, bukan ponsel.
     */
    public function share(ApiClient $apiClient, TokenStore $tokenStore): void
    {
        $this->error = null;
        $this->notice = null;

        if ($this->invoice->is_dirty) {
            $this->error = 'Invoice ini belum sampai ke server, jadi nomornya belum ada. Sinkronkan dulu dari Beranda.';

            return;
        }

        try {
            $shared = $apiClient->shareInvoice((string) $tokenStore->bookPublicId(), $this->invoice->public_id);
        } catch (\Throwable) {
            $this->error = 'Link invoice gagal diambil. Coba lagi saat sinyal membaik.';

            return;
        }

        Share::url("Invoice {$shared['invoice_number']}", $shared['message'], $shared['url']);

        // Server sudah menandai invoice ini terkirim; ponsel ikut menyesuaikan tanpa menunggu tarikan.
        $this->invoice->update(['status' => $shared['status']]);
        $this->invoice->refresh();
    }

    public function markAsPaid(InvoiceWriter $invoiceWriter): void
    {
        $data = $this->validate([
            'paymentAccountPublicId' => ['required', 'exists:accounts,public_id'],
            'paidAt' => ['required', 'date'],
        ], attributes: ['paymentAccountPublicId' => 'akun', 'paidAt' => 'tanggal']);

        $invoiceWriter->markAsPaid($this->invoice, $data['paymentAccountPublicId'], $data['paidAt']);

        $this->invoice->refresh();
        $this->notice = 'Invoice ditandai lunas dan pemasukannya sudah tercatat.';
    }

    public function remove(InvoiceWriter $invoiceWriter): void
    {
        if (! $invoiceWriter->remove($this->invoice)) {
            $this->error = 'Invoice yang sudah lunas tidak bisa dihapus.';

            return;
        }

        $this->redirect(route('invoices'));
    }

    public function draftTotal(): float
    {
        return collect($this->items)->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']);
    }

    /**
     * @return Collection<int, Client>
     */
    public function clients(): Collection
    {
        return Client::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    private function fillFormFromInvoice(): void
    {
        $this->clientPublicId = $this->invoice->client_public_id;
        $this->issueDate = $this->invoice->issue_date->toDateString();
        $this->dueDate = $this->invoice->due_date->toDateString();
        $this->notes = (string) $this->invoice->notes;
        $this->items = $this->invoice->items->map(fn ($item): array => [
            'description' => $item->description,
            'quantity' => (string) (float) $item->quantity,
            'unit_price' => (string) (int) $item->unit_price,
        ])->all() ?: [$this->blankItem()];
    }

    /**
     * @return array{description: string, quantity: string, unit_price: string}
     */
    private function blankItem(): array
    {
        return ['description' => '', 'quantity' => '1', 'unit_price' => ''];
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">{{ $this->isNew() ? 'Invoice baru' : $invoice->numberLabel() }}</p>
        <h1 class="masthead__title">
            {{ $this->isNew() ? 'Tagihan untuk siapa?' : ($invoice->client->name ?? 'Klien terhapus') }}
        </h1>
        <p class="masthead__note">
            {{ $this->isNew() ? 'Isi barisnya, nomornya dibuat server saat tersinkron.' : $invoice->statusLabel() }}
        </p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    @if ($notice)
        <p class="notice">{{ $notice }}</p>
    @endif

    @if (! $this->isNew())
        <section class="tape">
            <p class="tape__label">Total tagihan</p>
            <p class="numeral">{{ Rupiah::format((float) $invoice->total_amount) }}</p>

            <div class="entry" style="margin-top: 16px;">
                <div class="entry__label">
                    <p class="entry__title">Jatuh tempo</p>
                    <p class="entry__meta">Terbit {{ $invoice->issue_date->translatedFormat('j M Y') }}</p>
                </div>
                <span class="entry__amount {{ $invoice->isOverdue() ? 'numeral--debit' : '' }}">
                    {{ $invoice->due_date->translatedFormat('j M Y') }}
                </span>
            </div>

            @foreach ($invoice->items as $item)
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title">{{ $item->description }}</p>
                        <p class="entry__meta">{{ (float) $item->quantity }} × {{ Rupiah::format((float) $item->unit_price) }}</p>
                    </div>
                    <span class="entry__amount">{{ Rupiah::format($item->subtotal()) }}</span>
                </div>
            @endforeach

            @if ($invoice->notes)
                <p class="muted small" style="margin: 16px 0 0;">{{ $invoice->notes }}</p>
            @endif
        </section>

        @unless ($invoice->isPaid())
            <button class="button" type="button" wire:click="share" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="share">{{ $invoice->isDraft() ? 'Kirim ke klien' : 'Kirim ulang' }}</span>
                <span wire:loading wire:target="share">Menyiapkan link…</span>
            </button>
        @endunless

        @if ($invoice->isPayable())
            <section class="tape">
                <p class="tape__label">Tandai lunas</p>

                <form wire:submit="markAsPaid">
                    <div class="field">
                        <label class="field__label" for="paid-account">Masuk ke akun</label>
                        <select class="field__input" id="paid-account" wire:model="paymentAccountPublicId">
                            @foreach ($this->accounts() as $account)
                                <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('paymentAccountPublicId') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="paid-at">Tanggal bayar</label>
                        <input class="field__input" id="paid-at" type="date" wire:model="paidAt">
                        @error('paidAt') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <button class="button" type="submit">Simpan pelunasan</button>
                </form>
            </section>
        @elseif ($invoice->isDraft())
            <p class="muted small" style="margin-top: 12px;">
                Invoice bisa ditandai lunas setelah dikirim ke klien.
            </p>
        @endif
    @endif

    @if ($isEditing)
        <section class="tape">
            <p class="tape__label">{{ $this->isNew() ? 'Rincian' : 'Ubah rincian' }}</p>

            <form wire:submit="save">
                <div class="field">
                    <label class="field__label" for="client">Klien</label>
                    <select class="field__input" id="client" wire:model="clientPublicId">
                        @foreach ($this->clients() as $client)
                            <option value="{{ $client->public_id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('clientPublicId') <p class="field__hint">{{ $message }}</p> @enderror

                    @unless ($isAddingClient)
                        <button class="linkish" type="button" wire:click="$set('isAddingClient', true)">Klien baru</button>
                    @endunless
                </div>

                @if ($isAddingClient)
                    <div class="entry" style="display: block;">
                        <div class="field">
                            <label class="field__label" for="new-client-name">Nama klien baru</label>
                            <input class="field__input" id="new-client-name" type="text" placeholder="Toko Sinar"
                                   wire:model="newClientName">
                            @error('newClientName') <p class="field__hint">{{ $message }}</p> @enderror
                        </div>

                        <div class="field">
                            <label class="field__label" for="new-client-phone">Nomor WhatsApp</label>
                            <input class="field__input" id="new-client-phone" type="tel" inputmode="tel"
                                   placeholder="081234567890" wire:model="newClientPhone">
                            @error('newClientPhone') <p class="field__hint">{{ $message }}</p> @enderror
                        </div>

                        <button class="button button--quiet" type="button" wire:click="addClient">Simpan klien</button>
                        <button class="linkish" type="button" wire:click="$set('isAddingClient', false)" style="margin-top: 10px;">Batal</button>
                    </div>
                @endif

                <div class="field">
                    <label class="field__label" for="issue-date">Tanggal terbit</label>
                    <input class="field__input" id="issue-date" type="date" wire:model="issueDate">
                    @error('issueDate') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="due-date">Jatuh tempo</label>
                    <input class="field__input" id="due-date" type="date" wire:model="dueDate">
                    @error('dueDate') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <span class="field__label">Baris tagihan</span>

                @foreach ($items as $index => $item)
                    <div class="entry" style="display: block;">
                        <div class="field">
                            <input class="field__input" type="text" placeholder="Kopi 5 kg"
                                   wire:model="items.{{ $index }}.description">
                            @error("items.{$index}.description") <p class="field__hint">{{ $message }}</p> @enderror
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <div class="field" style="flex: 1;">
                                <label class="field__label" for="qty-{{ $index }}">Jumlah</label>
                                <input class="field__input field__input--numeral" id="qty-{{ $index }}" type="number"
                                       inputmode="decimal" min="0.01" step="0.01" wire:model="items.{{ $index }}.quantity">
                                @error("items.{$index}.quantity") <p class="field__hint">{{ $message }}</p> @enderror
                            </div>

                            <div class="field" style="flex: 1.4;">
                                <label class="field__label" for="price-{{ $index }}">Harga satuan</label>
                                <input class="field__input field__input--numeral" id="price-{{ $index }}" type="number"
                                       inputmode="numeric" min="0" step="1" placeholder="0"
                                       wire:model="items.{{ $index }}.unit_price">
                                @error("items.{$index}.unit_price") <p class="field__hint">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if (count($items) > 1)
                            <button class="linkish" type="button" wire:click="removeItem({{ $index }})">Hapus baris</button>
                        @endif
                    </div>
                @endforeach

                <button class="button button--quiet" type="button" wire:click="addItem" style="margin-top: 12px;">Tambah baris</button>

                <div class="entry" style="margin-top: 16px;">
                    <div class="entry__label"><p class="entry__title">Total sementara</p></div>
                    <span class="entry__amount">{{ Rupiah::format($this->draftTotal()) }}</span>
                </div>

                <div class="field">
                    <label class="field__label" for="invoice-notes">Catatan</label>
                    <input class="field__input" id="invoice-notes" type="text" placeholder="Pembayaran lewat transfer"
                           wire:model="notes">
                    @error('notes') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <button class="button" type="submit">{{ $this->isNew() ? 'Simpan invoice' : 'Simpan perubahan' }}</button>
            </form>

            @unless ($this->isNew())
                <button class="button button--quiet" type="button" wire:click="cancelEditing" style="margin-top: 10px;">Batal ubah</button>
            @endunless
        </section>
    @elseif (! $this->isNew())
        @unless ($invoice->isPaid())
            <button class="button button--quiet" type="button" wire:click="startEditing" style="margin-top: 10px;">Ubah rincian</button>

            <button class="button button--quiet" type="button" wire:click="remove" wire:confirm="Hapus invoice ini?"
                    style="margin-top: 10px;">Hapus invoice</button>
        @endunless
    @endif

    <a class="button button--quiet" href="{{ route('invoices') }}" wire:navigate style="margin-top: 10px;">Kembali</a>
</div>
