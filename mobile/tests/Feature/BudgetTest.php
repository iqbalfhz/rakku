<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\PlanGate;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => null]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Category::query()->create(['public_id' => '01m3bahan', 'name' => 'Bahan Baku', 'type' => 'expense']);
    Category::query()->create(['public_id' => '01m3listrik', 'name' => 'Listrik', 'type' => 'expense']);

    $this->travelTo('2026-10-15');
});

/**
 * Anggaran yang sudah tersinkron dari server.
 */
function syncedBudget(array $attributes = []): Budget
{
    return Budget::query()->create([
        'public_id' => '01m3anggaran',
        'category_public_id' => '01m3bahan',
        'amount' => 1_000_000,
        ...$attributes,
    ]);
}

/**
 * Pengeluaran pada kategori bahan baku.
 */
function spend(float $amount, string $date = '2026-10-05', string $category = '01m3bahan'): Transaction
{
    return Transaction::query()->create([
        'public_id' => 'trx-'.fake()->unique()->numerify('#####'),
        'account_public_id' => '01m3kas',
        'category_public_id' => $category,
        'type' => 'expense',
        'amount' => $amount,
        'transaction_date' => $date,
    ]);
}

it('sets a budget on the phone and queues it for the server', function () {
    Livewire::test('budgets')
        ->call('startAdding')
        ->set('categoryPublicId', '01m3bahan')
        ->set('amount', '1000000')
        ->call('save')
        ->assertHasNoErrors();

    $budget = Budget::query()->sole();

    expect((float) $budget->amount)->toBe(1_000_000.0)
        ->and($budget->category_public_id)->toBe('01m3bahan')
        ->and($budget->is_dirty)->toBeTrue();
});

it('measures the usage against this month only', function () {
    syncedBudget();
    spend(300_000);
    spend(500_000, '2026-09-20');

    Livewire::test('budgets')
        ->assertSee('Rp 300.000 dari Rp 1.000.000')
        ->assertSee('Terpakai 30%');
});

it('says plainly when the spending went past the limit', function () {
    syncedBudget(['amount' => 200_000]);
    spend(250_000);

    Livewire::test('budgets')
        ->assertSee('lewat batas')
        ->assertSee('gauge__fill--over', escape: false);
});

it('only offers categories that do not have a budget yet', function () {
    syncedBudget();

    Livewire::test('budgets')
        ->call('startAdding')
        ->assertSee('Listrik')
        ->assertDontSeeHtml('value="01m3bahan"');
});

it('keeps the alert switch out of reach for an account that is not premium', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);

    Livewire::test('budgets')
        ->call('startAdding')
        ->assertDontSee('Ingatkan saat mendekati batas')
        ->set('categoryPublicId', '01m3bahan')
        ->set('amount', '500000')
        ->set('alertEnabled', true)
        ->call('save');

    expect(Budget::query()->sole()->alert_enabled)->toBeFalse();
});

it('marks a deleted budget for the server instead of dropping it silently', function () {
    $budget = syncedBudget();

    Livewire::test('budgets')->call('remove', $budget->id);

    expect($budget->fresh()->is_deleted)->toBeTrue()
        ->and($budget->fresh()->is_dirty)->toBeTrue();
});

it('sends budgets along on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => [], 'budgets' => []]),
    ]);

    $budget = syncedBudget(['is_dirty' => true]);

    app(SyncEngine::class)->push();

    Http::assertSent(fn ($request): bool => ($request['budgets'][0]['public_id'] ?? null) === $budget->public_id);

    expect($budget->fresh()->is_dirty)->toBeFalse();
});

it('drops a budget that is no longer on the server', function () {
    syncedBudget();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'budgets' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Budget::query()->count())->toBe(0);
});

it('keeps a budget the phone has not sent yet, even when the server list is empty', function () {
    $budget = syncedBudget(['is_dirty' => true]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'budgets' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Budget::query()->sole()->is($budget))->toBeTrue();
});

it('brings budgets from the server into the phone', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'budgets' => [[
                'public_id' => '01m3anggaran',
                'category_public_id' => '01m3bahan',
                'amount' => 750_000,
                'alert_enabled' => true,
                'alert_threshold_percent' => 90,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    $budget = Budget::query()->sole();

    expect((float) $budget->amount)->toBe(750_000.0)
        ->and($budget->alert_threshold_percent)->toBe(90)
        ->and($budget->is_dirty)->toBeFalse();
});

it('survives a server that does not know about budgets yet', function () {
    syncedBudget();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Budget::query()->count())->toBe(1);
});
