<?php

use App\Enums\SubscriptionPlan;
use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());
    $this->book = $this->user->books()->first();
    $this->account = Account::factory()->for($this->book)->create(['initial_balance' => 0]);

    Sanctum::actingAs($this->user);
});

it('hands the phone the outstanding debts and their installments', function () {
    $debt = Debt::factory()->receivable()->for($this->book)->create(['amount' => 100_000]);
    $payment = DebtPayment::factory()->for($debt)->for($this->account)->create(['amount' => 40_000]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('debts'))->toHaveCount(1)
        ->and($response->json('debts.0.public_id'))->toBe($debt->public_id)
        ->and((float) $response->json('debts.0.remaining_amount'))->toBe(60_000.0)
        ->and($response->json('debts.0.status'))->toBe('unpaid')
        ->and($response->json('debt_payments'))->toHaveCount(1)
        ->and($response->json('debt_payments.0.public_id'))->toBe($payment->public_id)
        ->and($response->json('debt_payments.0.debt_public_id'))->toBe($debt->public_id);
});

it('keeps an old unpaid debt in the first pull no matter its age', function () {
    $this->travelTo(now()->subYears(2));
    $debt = Debt::factory()->payable()->for($this->book)->create();
    $this->travelBack();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('debts.0.public_id'))->toBe($debt->public_id);
});

it('leaves debts out for an account that is not premium', function () {
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();
    Debt::factory()->for($this->book)->create();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('debts'))->toBeEmpty()
        ->and($response->json('debt_payments'))->toBeEmpty();
});

it('passes a deleted debt on as a tombstone', function () {
    $this->travelTo('2026-10-01 08:00');
    $debt = Debt::factory()->for($this->book)->create();
    $since = now()->utc()->toIso8601ZuluString();

    $this->travelTo('2026-10-01 09:00');
    $debt->delete();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync?since={$since}")->assertSuccessful();

    expect($response->json('debts.0.public_id'))->toBe($debt->public_id)
        ->and($response->json('debts.0.is_deleted'))->toBeTrue();
});

it('accepts a debt written down offline', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'debts' => [[
            'public_id' => $publicId,
            'type' => 'receivable',
            'counterparty_name' => 'Bu Rina',
            'amount' => 150_000,
            'due_date' => '2026-11-01',
            'description' => 'Ambil beras 10 kg',
            'reminder_enabled' => true,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $debt = Debt::query()->where('public_id', $publicId)->sole();

    expect($debt->book_id)->toBe($this->book->id)
        ->and($debt->counterparty_name)->toBe('Bu Rina')
        ->and((float) $debt->remaining_amount)->toBe(150_000.0);
});

it('accepts an installment recorded offline and keeps the transaction id the phone made', function () {
    $debt = Debt::factory()->receivable()->for($this->book)->create(['amount' => 100_000]);
    $transactionPublicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'debt_payments' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'debt_public_id' => $debt->public_id,
            'account_public_id' => $this->account->public_id,
            'transaction_public_id' => $transactionPublicId,
            'amount' => 40_000,
            'payment_date' => '2026-10-01',
            'notes' => 'Bayar lewat transfer',
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $transaction = Transaction::query()->sole();

    expect($transaction->public_id)->toBe($transactionPublicId)
        ->and((float) $transaction->amount)->toBe(40_000.0)
        ->and((float) $debt->fresh()->remaining_amount)->toBe(60_000.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(40_000.0);
});

it('ignores an installment that was already recorded', function () {
    $debt = Debt::factory()->receivable()->for($this->book)->create(['amount' => 100_000]);
    $payload = [
        'public_id' => Str::lower((string) Str::ulid()),
        'debt_public_id' => $debt->public_id,
        'account_public_id' => $this->account->public_id,
        'amount' => 40_000,
        'payment_date' => '2026-10-01',
    ];

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [], 'debt_payments' => [$payload]]);
    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [], 'debt_payments' => [$payload]])
        ->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(DebtPayment::query()->count())->toBe(1)
        ->and((float) $debt->fresh()->remaining_amount)->toBe(60_000.0);
});

it('refuses to delete a debt that already has installments', function () {
    $debt = Debt::factory()->receivable()->for($this->book)->create(['amount' => 100_000]);
    DebtPayment::factory()->for($debt)->for($this->account)->create(['amount' => 10_000]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'debts' => [[
            'public_id' => $debt->public_id,
            'type' => $debt->type->value,
            'counterparty_name' => $debt->counterparty_name,
            'amount' => (float) $debt->amount,
            'is_deleted' => true,
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect($debt->fresh()->trashed())->toBeFalse();
});

it('turns down debts pushed by an account that is not premium', function () {
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'debts' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'type' => 'payable',
            'counterparty_name' => 'Pak Andi',
            'amount' => 50_000,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Debt::query()->count())->toBe(0);
});
