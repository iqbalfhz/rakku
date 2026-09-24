<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->book = $this->user->books()->first();
    $this->account = Account::factory()->for($this->book)->create();
    $this->transaction = Transaction::factory()->for($this->book)->for($this->account)->create();

    Sanctum::actingAs($this->user);
});

/**
 * Alamat unggah foto untuk sebuah transaksi.
 */
function receiptUrl(string $bookPublicId, string $transactionPublicId): string
{
    return "/api/v1/books/{$bookPublicId}/transactions/{$transactionPublicId}/receipt";
}

it('accepts a receipt photo taken on the phone', function () {
    $this->postJson(receiptUrl($this->book->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertSuccessful()->assertJson(['has_receipt' => true]);

    $path = $this->transaction->fresh()->receipt_photo_path;

    expect($path)->toStartWith('receipts/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('replaces the previous photo instead of leaving both behind', function () {
    Storage::disk('local')->put('receipts/lama.jpg', 'foto lama');
    $this->transaction->update(['receipt_photo_path' => 'receipts/lama.jpg']);

    $this->postJson(receiptUrl($this->book->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk-baru.jpg'),
    ])->assertSuccessful();

    expect(Storage::disk('local')->exists('receipts/lama.jpg'))->toBeFalse()
        ->and(Storage::disk('local')->exists($this->transaction->fresh()->receipt_photo_path))->toBeTrue();
});

it('refuses anything that is not an image', function () {
    $this->postJson(receiptUrl($this->book->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->create('catatan.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors(['receipt']);

    expect($this->transaction->fresh()->receipt_photo_path)->toBeNull();
});

it('refuses a photo that is too large to be a receipt', function () {
    $this->postJson(receiptUrl($this->book->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk.jpg')->size(6000),
    ])->assertStatus(422)->assertJsonValidationErrors(['receipt']);
});

it('will not attach a photo to a transaction from another book', function () {
    $foreignTransaction = Transaction::factory()->create();

    $this->postJson(receiptUrl($this->book->public_id, $foreignTransaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertNotFound();

    expect($foreignTransaction->fresh()->receipt_photo_path)->toBeNull();
});

it('will not let a stranger upload into someone else book', function () {
    $foreignBook = User::factory()->create()->books()->first();

    $this->postJson(receiptUrl($foreignBook->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertNotFound();
});

it('marks the transaction as having a receipt in the next pull', function () {
    $this->postJson(receiptUrl($this->book->public_id, $this->transaction->public_id), [
        'receipt' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertSuccessful();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync");

    expect($response->json('transactions.0.has_receipt'))->toBeTrue();
});

it('hands the photo back when the phone asks for it', function () {
    Storage::disk('local')->put('receipts/struk.jpg', 'isi foto struk');
    $this->transaction->update(['receipt_photo_path' => 'receipts/struk.jpg']);

    $response = $this->get(receiptUrl($this->book->public_id, $this->transaction->public_id));

    $response->assertSuccessful();
    expect($response->streamedContent())->toBe('isi foto struk');
});

it('answers plainly when a transaction has no photo', function () {
    $this->get(receiptUrl($this->book->public_id, $this->transaction->public_id))->assertNotFound();
});

it('does not hand out a photo from someone else book', function () {
    $foreignBook = User::factory()->create()->books()->first();

    $this->get(receiptUrl($foreignBook->public_id, $this->transaction->public_id))->assertNotFound();
});
