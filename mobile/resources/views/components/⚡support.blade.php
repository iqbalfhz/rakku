<?php

use App\Services\ApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Native\Mobile\Facades\Camera;

new class extends Component
{
    /**
     * Nomor tiket yang sedang dibuka. Null berarti sedang melihat daftarnya.
     */
    public ?string $ticketNumber = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $tickets = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $ticket = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $messages = [];

    public bool $isComposing = false;

    public string $subject = '';

    public string $body = '';

    public ?string $attachmentPath = null;

    public ?string $error = null;

    public function mount(ApiClient $apiClient, ?string $ticketNumber = null): void
    {
        $this->ticketNumber = $ticketNumber;

        $this->load($apiClient);
    }

    /**
     * Seluruh isi layar ini ada di server: balasan admin tidak pernah lahir di ponsel,
     * jadi tidak ada yang layak disimpan sebagai salinan yang bisa basi.
     */
    public function load(ApiClient $apiClient): void
    {
        $this->error = null;

        try {
            if ($this->ticketNumber === null) {
                $this->tickets = $apiClient->supportTickets()['tickets'];

                return;
            }

            $thread = $apiClient->supportTicket($this->ticketNumber);

            $this->ticket = $thread['ticket'];
            $this->messages = $thread['messages'];
        } catch (\Throwable) {
            $this->error = 'Bantuan butuh sinyal. Coba lagi sebentar.';
        }
    }

    public function startComposing(): void
    {
        $this->error = null;
        $this->isComposing = true;
    }

    public function cancelComposing(): void
    {
        $this->resetValidation();

        $this->isComposing = false;
        $this->subject = '';
        $this->body = '';
        $this->removeAttachment();
    }

    public function takePhoto(): void
    {
        Camera::getPhoto();
    }

    #[On('native:Native\Mobile\Events\Camera\PhotoTaken')]
    public function photoTaken(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $storedPath = 'support-attachments/'.Str::lower((string) Str::ulid()).'.jpg';

        Storage::disk('local')->put($storedPath, file_get_contents($path));

        $this->attachmentPath = $storedPath;
    }

    public function removeAttachment(): void
    {
        if ($this->attachmentPath !== null) {
            Storage::disk('local')->delete($this->attachmentPath);
        }

        $this->attachmentPath = null;
    }

    /**
     * Buka aduan baru, lalu langsung masuk ke percakapannya.
     */
    public function open(ApiClient $apiClient): void
    {
        $data = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ], attributes: ['subject' => 'judul', 'body' => 'pesan']);

        $this->error = null;

        try {
            $created = $apiClient->openSupportTicket($data['subject'], $data['body'], $this->absoluteAttachmentPath());
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->removeAttachment();

        $this->redirect(route('support.show', $created['ticket']['ticket_number']));
    }

    public function reply(ApiClient $apiClient): void
    {
        $data = $this->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], attributes: ['body' => 'pesan']);

        $this->error = null;

        try {
            $apiClient->replyToSupportTicket($this->ticketNumber, $data['body'], $this->absoluteAttachmentPath());
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->removeAttachment();
        $this->body = '';

        $this->load($apiClient);
    }

    public function isThread(): bool
    {
        return $this->ticketNumber !== null;
    }

    public function attachmentDataUri(): ?string
    {
        if ($this->attachmentPath === null || ! Storage::disk('local')->exists($this->attachmentPath)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($this->attachmentPath));
    }

    public function momentOf(?string $timestamp): string
    {
        return $timestamp === null ? '' : CarbonImmutable::parse($timestamp)->translatedFormat('j M Y, H:i');
    }

    private function absoluteAttachmentPath(): ?string
    {
        return $this->attachmentPath === null ? null : Storage::disk('local')->path($this->attachmentPath);
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Bantuan</p>
        <h1 class="masthead__title">
            @if ($this->isThread())
                {{ $ticket['subject'] ?? 'Percakapan' }}
            @else
                Ada yang<br>mengganjal?
            @endif
        </h1>
        <p class="masthead__note">
            @if ($this->isThread() && $ticket)
                {{ $ticket['ticket_number'] }} · {{ $ticket['status_label'] }}
            @else
                Tulis di sini. Kami baca dan balas lewat aplikasi ini juga.
            @endif
        </p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    @if ($this->isThread())
        <section class="tape">
            <p class="tape__label">Percakapan</p>

            @forelse ($messages as $message)
                <div class="entry" style="display: block;">
                    <p class="entry__meta" style="margin: 0 0 6px;">
                        <span class="stamp {{ $message['from_admin'] ? '' : 'stamp--muted' }}">
                            {{ $message['from_admin'] ? 'Admin' : 'Anda' }}
                        </span>
                        · {{ $this->momentOf($message['sent_at']) }}
                        @if ($message['has_attachment']) · ada lampiran @endif
                    </p>
                    <p class="entry__title" style="margin: 0;">{{ $message['body'] }}</p>
                </div>
            @empty
                <p class="entry__title muted">Belum ada pesan.</p>
            @endforelse
        </section>

        <section class="tape">
            <p class="tape__label">Balas</p>

            <form wire:submit="reply">
                <div class="field">
                    <label class="field__label" for="reply-body">Pesan</label>
                    <input class="field__input" id="reply-body" type="text" placeholder="Tulis balasan Anda"
                           wire:model="body">
                    @error('body') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <span class="field__label">Lampiran</span>

                    @if ($this->attachmentDataUri())
                        <img class="receipt" src="{{ $this->attachmentDataUri() }}" alt="Lampiran">
                        <div class="entry" style="border-bottom: 0;">
                            <span class="stamp">Foto terpasang</span>
                            <button class="linkish" type="button" wire:click="removeAttachment">Hapus foto</button>
                        </div>
                    @else
                        <button class="button button--quiet" type="button" wire:click="takePhoto">Fotokan layarnya</button>
                    @endif
                </div>

                <button class="button" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="reply">Kirim balasan</span>
                    <span wire:loading wire:target="reply">Mengirim…</span>
                </button>
            </form>
        </section>

        <a class="button button--quiet" href="{{ route('support') }}" wire:navigate>Semua aduan</a>
    @else
        <section class="tape">
            <p class="tape__label">Aduan Anda</p>

            @forelse ($tickets as $row)
                <a class="entry" href="{{ route('support.show', $row['ticket_number']) }}" wire:navigate
                   style="color: inherit; text-decoration: none;">
                    <div class="entry__label">
                        <p class="entry__title">{{ $row['subject'] }}</p>
                        <p class="entry__meta">{{ $row['ticket_number'] }} · {{ $this->momentOf($row['last_message_at']) }}</p>
                    </div>
                    <span class="stamp {{ $row['status'] === 'answered' ? '' : 'stamp--muted' }}">{{ $row['status_label'] }}</span>
                </a>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada aduan</p>
                        <p class="entry__meta">Semoga memang tidak ada yang bermasalah</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse

            @unless ($isComposing)
                <button class="button" type="button" wire:click="startComposing" style="margin-top: 20px;">Tulis aduan</button>
            @endunless
        </section>

        @if ($isComposing)
            <section class="tape">
                <p class="tape__label">Aduan baru</p>

                <form wire:submit="open">
                    <div class="field">
                        <label class="field__label" for="ticket-subject">Judul</label>
                        <input class="field__input" id="ticket-subject" type="text" placeholder="Saldo tidak cocok"
                               wire:model="subject">
                        @error('subject') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="ticket-body">Ceritakan masalahnya</label>
                        <input class="field__input" id="ticket-body" type="text"
                               placeholder="Setelah sinkron, saldo kas saya berkurang sendiri" wire:model="body">
                        @error('body') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <span class="field__label">Lampiran</span>

                        @if ($this->attachmentDataUri())
                            <img class="receipt" src="{{ $this->attachmentDataUri() }}" alt="Lampiran">
                            <div class="entry" style="border-bottom: 0;">
                                <span class="stamp">Foto terpasang</span>
                                <button class="linkish" type="button" wire:click="removeAttachment">Hapus foto</button>
                            </div>
                        @else
                            <button class="button button--quiet" type="button" wire:click="takePhoto">Fotokan layarnya</button>
                        @endif
                    </div>

                    <button class="button" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="open">Kirim aduan</span>
                        <span wire:loading wire:target="open">Mengirim…</span>
                    </button>
                </form>

                <button class="button button--quiet" type="button" wire:click="cancelComposing" style="margin-top: 10px;">Batal</button>
            </section>
        @endif

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
    @endif
</div>
