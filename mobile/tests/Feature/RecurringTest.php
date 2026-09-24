<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
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
    Category::query()->create(['public_id' => '01m3listrik', 'name' => 'Listrik', 'type' => 'expense']);

    $this->travelTo('2026-10-15');
});

/**
 * Jadwal yang sudah tersinkron dari server.
 */
function syncedSchedule(array $attributes = []): RecurringTransaction
{
    return RecurringTransaction::query()->create([
        'public_id' => '01m3jadwal',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 350_000,
        'description' => 'Bayar listrik',
        'frequency' => 'monthly',
        'start_date' => '2026-10-05',
        'next_run_date' => '2026-11-05',
        ...$attributes,
    ]);
}

it('sets up a schedule on the phone and queues it for the server', function () {
    Livewire::test('recurring')
        ->call('startAdding')
        ->set('type', 'expense')
        ->set('accountPublicId', '01m3kas')
        ->set('amount', '350000')
        ->set('description', 'Bayar listrik')
        ->set('frequency', 'monthly')
        ->set('startDate', '2026-11-05')
        ->call('save')
        ->assertHasNoErrors();

    $schedule = RecurringTransaction::query()->sole();

    expect($schedule->description)->toBe('Bayar listrik')
        ->and($schedule->frequency)->toBe('monthly')
        ->and($schedule->is_active)->toBeTrue()
        ->and($schedule->is_dirty)->toBeTrue()
        ->and($schedule->next_run_date)->toBeNull();
});

it('says the schedule has not started yet while the server has not seen it', function () {
    syncedSchedule(['next_run_date' => null, 'is_dirty' => true]);

    Livewire::test('recurring')->assertSee('Mulai 5 Okt 2026');
});

it('refuses an end date before the start', function () {
    Livewire::test('recurring')
        ->call('startAdding')
        ->set('accountPublicId', '01m3kas')
        ->set('amount', '100000')
        ->set('startDate', '2026-11-05')
        ->set('endDate', '2026-10-05')
        ->call('save')
        ->assertHasErrors('endDate');

    expect(RecurringTransaction::query()->count())->toBe(0);
});

it('pauses and resumes a schedule', function () {
    $schedule = syncedSchedule();

    Livewire::test('recurring')->call('togglePause', $schedule->id);

    expect($schedule->fresh()->is_active)->toBeFalse()
        ->and($schedule->fresh()->is_dirty)->toBeTrue();

    Livewire::test('recurring')->call('togglePause', $schedule->id);

    expect($schedule->fresh()->is_active)->toBeTrue();
});

it('marks a deleted schedule for the server instead of dropping it silently', function () {
    $schedule = syncedSchedule();

    Livewire::test('recurring')->call('remove', $schedule->id);

    expect($schedule->fresh()->is_deleted)->toBeTrue()
        ->and($schedule->fresh()->is_dirty)->toBeTrue();
});

it('keeps the schedule screen shut for an account that is not premium', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);

    Livewire::test('recurring')->assertSee('Fitur premium')->assertDontSee('Tambah jadwal');
});

it('sends schedules along on the next sync without claiming the next run date', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::response(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z']),
    ]);

    $schedule = syncedSchedule(['is_dirty' => true]);

    app(SyncEngine::class)->push();

    Http::assertSent(function ($request): bool {
        $sent = $request['recurring_transactions'][0] ?? [];

        return ($sent['description'] ?? null) === 'Bayar listrik'
            && ! array_key_exists('next_run_date', $sent);
    });

    expect($schedule->fresh()->is_dirty)->toBeFalse();
});

it('brings schedules from the server into the phone', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'recurring_transactions' => [[
                'public_id' => '01m3jadwal',
                'account_public_id' => '01m3kas',
                'category_public_id' => '01m3listrik',
                'type' => 'expense',
                'amount' => 350_000,
                'description' => 'Bayar listrik',
                'frequency' => 'monthly',
                'start_date' => '2026-10-05',
                'next_run_date' => '2026-11-05',
                'end_date' => null,
                'is_active' => true,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    $schedule = RecurringTransaction::query()->sole();

    expect($schedule->next_run_date->toDateString())->toBe('2026-11-05')
        ->and($schedule->is_dirty)->toBeFalse();
});

it('keeps a schedule the phone has not sent yet, even when the server list is empty', function () {
    $schedule = syncedSchedule(['is_dirty' => true]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'recurring_transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(RecurringTransaction::query()->sole()->is($schedule))->toBeTrue();
});

it('survives a server that does not know about schedules yet', function () {
    syncedSchedule();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(RecurringTransaction::query()->count())->toBe(1);
});
