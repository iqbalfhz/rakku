<?php

use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = $this->user->books()->first();
    $this->cash = Account::factory()->for($this->book)->create(['initial_balance' => 500_000]);
    $this->bank = Account::factory()->for($this->book)->create(['initial_balance' => 0]);

    Sanctum::actingAs($this->user);
});

it('hands the phone the transfers on the book', function () {
    $transfer = Transfer::factory()->for($this->book)
        ->for($this->cash, 'fromAccount')
        ->for($this->bank, 'toAccount')
        ->create(['amount' => 200_000]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('transfers'))->toHaveCount(1)
        ->and($response->json('transfers.0.public_id'))->toBe($transfer->public_id)
        ->and($response->json('transfers.0.from_account_public_id'))->toBe($this->cash->public_id)
        ->and($response->json('transfers.0.to_account_public_id'))->toBe($this->bank->public_id)
        ->and((float) $response->json('transfers.0.amount'))->toBe(200_000.0);
});

it('accepts a transfer recorded offline and moves both balances', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => $publicId,
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $this->bank->public_id,
            'amount' => 200_000,
            'description' => 'Setor ke bank',
            'transfer_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $transfer = Transfer::query()->where('public_id', $publicId)->sole();

    expect($transfer->book_id)->toBe($this->book->id)
        ->and($transfer->description)->toBe('Setor ke bank')
        ->and((float) $this->cash->fresh()->current_balance)->toBe(300_000.0)
        ->and((float) $this->bank->fresh()->current_balance)->toBe(200_000.0);
});

it('keeps a transfer out of the profit and loss report', function () {
    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $this->bank->public_id,
            'amount' => 200_000,
            'transfer_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    // Uang hanya berpindah tempat: tidak ada pemasukan dan tidak ada pengeluaran.
    expect($this->book->transactions()->count())->toBe(0);
});

it('refuses a transfer into the very same account', function () {
    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $this->cash->public_id,
            'amount' => 200_000,
            'transfer_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJsonValidationErrorFor('transfers.0.to_account_public_id');

    expect(Transfer::query()->count())->toBe(0);
});

it('refuses a transfer touching an account from another book', function () {
    $foreignAccount = Account::factory()->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $foreignAccount->public_id,
            'amount' => 200_000,
            'transfer_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Transfer::query()->count())->toBe(0);
});

it('puts the money back when the phone cancels a transfer', function () {
    $transfer = Transfer::factory()->for($this->book)
        ->for($this->cash, 'fromAccount')
        ->for($this->bank, 'toAccount')
        ->create(['amount' => 200_000]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => $transfer->public_id,
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $this->bank->public_id,
            'amount' => (float) $transfer->amount,
            'transfer_date' => $transfer->transfer_date->toDateString(),
            'is_deleted' => true,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and((float) $this->cash->fresh()->current_balance)->toBe(500_000.0)
        ->and((float) $this->bank->fresh()->current_balance)->toBe(0.0);
});

it('passes a cancelled transfer on as a tombstone', function () {
    $this->travelTo('2026-10-01 08:00');
    $transfer = Transfer::factory()->for($this->book)
        ->for($this->cash, 'fromAccount')
        ->for($this->bank, 'toAccount')
        ->create();
    $since = now()->utc()->toIso8601ZuluString();

    $this->travelTo('2026-10-01 09:00');
    $transfer->delete();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync?since={$since}")->assertSuccessful();

    expect($response->json('transfers.0.public_id'))->toBe($transfer->public_id)
        ->and($response->json('transfers.0.is_deleted'))->toBeTrue();
});

it('never resurrects a transfer that was cancelled on the server', function () {
    $transfer = Transfer::factory()->for($this->book)
        ->for($this->cash, 'fromAccount')
        ->for($this->bank, 'toAccount')
        ->create();
    $transfer->delete();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'transfers' => [[
            'public_id' => $transfer->public_id,
            'from_account_public_id' => $this->cash->public_id,
            'to_account_public_id' => $this->bank->public_id,
            'amount' => 100_000,
            'transfer_date' => '2026-10-01',
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Transfer::query()->where('public_id', $transfer->public_id)->exists())->toBeFalse();
});
