<?php

use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Daftar aduan seperti yang dikirim server.
 *
 * @param  list<array<string, mixed>>  $tickets
 */
function fakeTicketList(array $tickets = []): void
{
    Http::fake(['*/api/v1/support-tickets' => Http::response(['tickets' => $tickets])]);
}

/**
 * Foto yang sudah dijepret kamera.
 */
function takenScreenshot(): string
{
    Storage::disk('local')->put('kamera-sementara.jpg', 'hasil jepretan');

    return Storage::disk('local')->path('kamera-sementara.jpg');
}

it('lets a user complain without leaving the app', function () {
    Http::fake([
        '*/api/v1/support-tickets' => Http::sequence()
            ->push(['tickets' => []])
            ->push(['ticket' => ['ticket_number' => 'TKT-2026-0001']], 201),
    ]);

    Livewire::test('support')
        ->call('startComposing')
        ->set('subject', 'Saldo tidak cocok')
        ->set('body', 'Setelah sinkron, saldo kas saya berkurang sendiri.')
        ->call('open')
        ->assertHasNoErrors()
        ->assertRedirect(route('support.show', 'TKT-2026-0001'));
});

it('sends the screenshot along with the complaint', function () {
    Http::fake([
        '*/api/v1/support-tickets' => Http::sequence()
            ->push(['tickets' => []])
            ->push(['ticket' => ['ticket_number' => 'TKT-2026-0001']], 201),
    ]);

    $component = Livewire::test('support')
        ->call('startComposing')
        ->call('photoTaken', takenScreenshot());

    $storedPath = $component->get('attachmentPath');

    $component->set('subject', 'Layar aneh')
        ->set('body', 'Begini tampilannya.')
        ->call('open')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => $request->isMultipart());

    // Salinan di ponsel dibuang setelah terkirim.
    expect(Storage::disk('local')->exists($storedPath))->toBeFalse();
});

it('shows who said what in the conversation', function () {
    Http::fake([
        '*/api/v1/support-tickets/TKT-2026-0001' => Http::response([
            'ticket' => [
                'ticket_number' => 'TKT-2026-0001',
                'subject' => 'Saldo tidak cocok',
                'status' => 'answered',
                'status_label' => 'Sudah dijawab',
                'last_message_at' => '2026-09-24T03:00:00Z',
            ],
            'messages' => [
                ['body' => 'Pertanyaan saya', 'from_admin' => false, 'author_name' => 'Budi', 'has_attachment' => false, 'sent_at' => '2026-09-24T03:00:00Z'],
                ['body' => 'Jawaban admin', 'from_admin' => true, 'author_name' => 'Admin', 'has_attachment' => false, 'sent_at' => '2026-09-24T04:00:00Z'],
            ],
        ]),
    ]);

    Livewire::test('support', ['ticketNumber' => 'TKT-2026-0001'])
        ->assertSee('Saldo tidak cocok')
        ->assertSee('Sudah dijawab')
        ->assertSee('Pertanyaan saya')
        ->assertSee('Jawaban admin')
        ->assertSee('Admin');
});

it('will not send an empty complaint', function () {
    fakeTicketList();

    Livewire::test('support')
        ->call('startComposing')
        ->call('open')
        ->assertHasErrors(['subject', 'body']);

    Http::assertSentCount(1);
});

it('passes on the reason when the server refuses', function () {
    Http::fake([
        '*/api/v1/support-tickets' => Http::sequence()
            ->push(['tickets' => []])
            ->push(['errors' => ['body' => ['Pesan terlalu panjang.']]], 422),
    ]);

    Livewire::test('support')
        ->call('startComposing')
        ->set('subject', 'Judul')
        ->set('body', 'Isi')
        ->call('open')
        ->assertSet('error', 'Pesan terlalu panjang.');
});

it('admits it needs a signal instead of showing an empty list', function () {
    Http::fake(['*/api/v1/support-tickets' => Http::response('', 500)]);

    Livewire::test('support')->assertSee('Bantuan butuh sinyal');
});

it('says so plainly when there is nothing to complain about yet', function () {
    fakeTicketList();

    Livewire::test('support')->assertSee('Belum ada aduan');
});

it('offers the way in from the account screen', function () {
    Livewire::test('account')->assertSee('Tanya atau laporkan masalah ke admin');
});
