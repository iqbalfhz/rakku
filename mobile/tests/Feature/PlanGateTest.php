<?php

use App\Services\PlanGate;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Jawaban tarikan dengan status langganan tertentu.
 */
function fakePullWithPlan(bool $isPremium, ?string $expiresAt): void
{
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'plan' => ['is_premium' => $isPremium, 'expires_at' => $expiresAt],
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);
}

it('remembers the subscription status handed over by the server', function () {
    fakePullWithPlan(true, '2026-12-31T16:59:59Z');

    app(SyncEngine::class)->pull();

    expect(app(PlanGate::class)->isPremium())->toBeTrue();
});

it('locks premium by itself once the date has passed, without asking the server', function () {
    $this->travelTo('2026-10-01');
    fakePullWithPlan(true, '2026-10-05T16:59:59Z');
    app(SyncEngine::class)->pull();

    expect(app(PlanGate::class)->isPremium())->toBeTrue();

    // Ponsel dibawa berhari-hari tanpa sinyal; tidak ada kabar baru dari server.
    $this->travelTo('2026-10-06 17:00');

    expect(app(PlanGate::class)->isPremium())->toBeFalse();
});

it('keeps premium open when there is no expiry date at all', function () {
    fakePullWithPlan(true, null);

    app(SyncEngine::class)->pull();

    $this->travelTo('2030-01-01');

    expect(app(PlanGate::class)->isPremium())->toBeTrue();
});

it('treats a free account as free', function () {
    fakePullWithPlan(false, null);

    app(SyncEngine::class)->pull();

    expect(app(PlanGate::class)->isPremium())->toBeFalse();
});

it('starts locked on a phone that has never synced', function () {
    expect(app(PlanGate::class)->isPremium())->toBeFalse();
});

it('survives a server that does not mention the plan at all', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(app(PlanGate::class)->isPremium())->toBeFalse()
        ->and($this->tokenStore->lastSyncedAt())->toBe('2026-10-01T03:00:00Z');
});
