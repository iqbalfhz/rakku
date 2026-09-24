<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 100_000]);
});

/**
 * Jawaban sinkron yang normal: dorongan diterima, lalu tarikan kosong.
 */
function fakeSyncExchange(): void
{
    Http::fake([
        '*/api/v1/books/*/transactions/*/receipt' => Http::response(['has_receipt' => true]),
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => []]),
    ]);
}

/**
 * Transaksi dengan foto yang masih menunggu diunggah.
 */
function transactionWithPhoto(string $publicId = '01m3trx'): Transaction
{
    Storage::disk('local')->put('receipts/foto.jpg', 'isi foto struk');

    return Transaction::query()->create([
        'public_id' => $publicId,
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'receipt_local_path' => 'receipts/foto.jpg',
        'is_dirty' => true,
    ]);
}

it('keeps the photo taken by the camera inside the app storage', function () {
    $cameraPath = Storage::disk('local')->path('kamera-sementara.jpg');
    Storage::disk('local')->put('kamera-sementara.jpg', 'hasil jepretan');

    $component = Livewire::test('record')->call('photoTaken', $cameraPath);

    $storedPath = $component->get('receiptPath');

    expect($storedPath)->toStartWith('receipts/')
        ->and(Storage::disk('local')->get($storedPath))->toBe('hasil jepretan');
});

it('attaches the photo to the transaction it was taken for', function () {
    Http::fake();
    $cameraPath = Storage::disk('local')->path('kamera-sementara.jpg');
    Storage::disk('local')->put('kamera-sementara.jpg', 'hasil jepretan');

    Livewire::test('record')
        ->call('photoTaken', $cameraPath)
        ->set('amount', '25000')
        ->set('accountPublicId', '01m3kas')
        ->set('transactionDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    $transaction = Transaction::query()->sole();

    expect($transaction->receipt_local_path)->toStartWith('receipts/')
        ->and($transaction->has_receipt)->toBeFalse();
});

it('uploads the waiting photo on the next sync and frees the space', function () {
    fakeSyncExchange();
    $transaction = transactionWithPhoto();

    app(SyncEngine::class)->sync();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), "/transactions/{$transaction->public_id}/receipt"));

    $transaction = $transaction->fresh();

    expect($transaction->has_receipt)->toBeTrue()
        ->and($transaction->receipt_local_path)->toBeNull()
        ->and(Storage::disk('local')->exists('receipts/foto.jpg'))->toBeFalse();
});

it('keeps the photo waiting when the upload fails', function () {
    Http::fake([
        '*/api/v1/books/*/transactions/*/receipt' => Http::response('', 500),
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => []]),
    ]);

    $transaction = transactionWithPhoto();

    app(SyncEngine::class)->sync();

    expect($transaction->fresh()->has_receipt)->toBeFalse()
        ->and($transaction->fresh()->receipt_local_path)->toBe('receipts/foto.jpg')
        ->and(Storage::disk('local')->exists('receipts/foto.jpg'))->toBeTrue();
});

it('gives up on a photo whose file has vanished from the phone', function () {
    fakeSyncExchange();
    $transaction = transactionWithPhoto();
    Storage::disk('local')->delete('receipts/foto.jpg');

    app(SyncEngine::class)->sync();

    expect($transaction->fresh()->receipt_local_path)->toBeNull();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/receipt'));
});

it('does not upload again for a transaction that already has its photo on the server', function () {
    fakeSyncExchange();
    Storage::disk('local')->put('receipts/foto.jpg', 'isi foto struk');
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'receipt_local_path' => 'receipts/foto.jpg',
        'has_receipt' => true,
    ]);

    app(SyncEngine::class)->uploadReceipts();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/receipt'));
});

it('shows the photo that is still on the phone', function () {
    Storage::disk('local')->put('receipts/foto.jpg', 'isi foto struk');
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'receipt_local_path' => 'receipts/foto.jpg',
    ]);

    Livewire::test('record', ['publicId' => '01m3trx'])
        ->assertSee('data:image/jpeg;base64,'.base64_encode('isi foto struk'), escape: false);
});

it('fetches an old photo from the server only when asked', function () {
    Http::fake(['*/transactions/*/receipt' => Http::response('foto dari server')]);
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'has_receipt' => true,
    ]);

    $component = Livewire::test('record', ['publicId' => '01m3trx'])->assertSee('Lihat foto struk');

    Http::assertNothingSent();

    $component->call('downloadPhoto')->assertSee('data:image/jpeg;base64,'.base64_encode('foto dari server'), escape: false);

    expect(Transaction::query()->sole()->receipt_local_path)->toStartWith('receipts/');
});

it('says so when the old photo cannot be fetched', function () {
    Http::fake(['*/transactions/*/receipt' => Http::response('', 500)]);
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'has_receipt' => true,
    ]);

    Livewire::test('record', ['publicId' => '01m3trx'])
        ->call('downloadPhoto')
        ->assertSet('downloadError', 'Foto gagal diambil. Coba lagi saat sinyal membaik.');
});
