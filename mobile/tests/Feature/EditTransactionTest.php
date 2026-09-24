<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');

    $this->account = Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 75_000]);
    $this->otherAccount = Account::query()->create(['public_id' => '01m3bca', 'name' => 'BCA', 'type' => 'bank', 'current_balance' => 500_000]);
    Category::query()->create(['public_id' => '01m3belanja', 'name' => 'Belanja', 'type' => 'expense']);

    $this->transaction = Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'description' => 'Beli kertas',
        'transaction_date' => '2026-10-01',
    ]);
});

it('opens the form already filled with what was written before', function () {
    Livewire::test('record', ['publicId' => '01m3trx'])
        ->assertSet('amount', '25000')
        ->assertSet('description', 'Beli kertas')
        ->assertSet('accountPublicId', '01m3kas')
        ->assertSee('Simpan perubahan');
});

it('corrects the balance when the amount changes', function () {
    Http::fake();

    Livewire::test('record', ['publicId' => '01m3trx'])
        ->set('amount', '40000')
        ->call('save')
        ->assertRedirect(route('home'));

    expect((float) $this->transaction->fresh()->amount)->toBe(40_000.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(60_000.0)
        ->and($this->transaction->fresh()->is_dirty)->toBeTrue();
});

it('moves the money when the entry is pointed at another account', function () {
    Http::fake();

    Livewire::test('record', ['publicId' => '01m3trx'])
        ->set('accountPublicId', '01m3bca')
        ->call('save');

    expect((float) $this->account->fresh()->current_balance)->toBe(100_000.0)
        ->and((float) $this->otherAccount->fresh()->current_balance)->toBe(475_000.0);
});

it('turns an expense into income without leaving the balance crooked', function () {
    Http::fake();

    Livewire::test('record', ['publicId' => '01m3trx'])
        ->set('type', 'income')
        ->call('save');

    expect((float) $this->account->fresh()->current_balance)->toBe(125_000.0);
});

it('sends the correction to the server on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => []]),
    ]);

    Livewire::test('record', ['publicId' => '01m3trx'])->set('amount', '40000')->call('save');

    app(SyncEngine::class)->sync();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request['transactions'][0]['public_id'] === '01m3trx'
        && (float) $request['transactions'][0]['amount'] === 40_000.0);
});

it('refuses to open an entry that was already deleted', function () {
    $this->transaction->update(['is_deleted' => true]);

    Livewire::test('record', ['publicId' => '01m3trx'])->assertStatus(404);
});
