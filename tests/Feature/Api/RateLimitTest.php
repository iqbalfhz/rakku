<?php

use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(SubscriptionPayment::proofDisk());

    $this->user = User::factory()->create();

    Sanctum::actingAs($this->user);
});

it('stops a flood of transfer proofs from one account', function () {
    // Pengajuan kedua dan seterusnya ditolak 409 karena yang pertama masih menunggu;
    // yang diuji di sini adalah pintu di depannya, bukan aturan itu.
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/subscription/payments', [
            'package' => 'monthly',
            'proof' => UploadedFile::fake()->image('struk.jpg'),
        ]);
    }

    $this->postJson('/api/v1/subscription/payments', [
        'package' => 'monthly',
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertStatus(429);
});

it('stops an app that will not stop complaining', function () {
    foreach (range(1, 20) as $ignored) {
        $this->postJson('/api/v1/device-reports', ['reports' => []])->assertSuccessful();
    }

    $this->postJson('/api/v1/device-reports', ['reports' => []])->assertStatus(429);
});

it('counts the limit per account, not for everyone at once', function () {
    foreach (range(1, 20) as $ignored) {
        $this->postJson('/api/v1/device-reports', ['reports' => []]);
    }

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/device-reports', ['reports' => []])->assertSuccessful();
});

it('leaves room for a sync that uploads a pile of receipts', function () {
    $book = $this->user->books()->first();

    // Jauh lebih banyak dari satu sinkronisasi yang wajar, dan masih lolos.
    foreach (range(1, 60) as $ignored) {
        $this->getJson("/api/v1/books/{$book->public_id}/sync")->assertSuccessful();
    }
});
