<?php

use App\Filament\App\Pages\Tenancy\EditBookProfile;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('does not allow deleting the default book', function () {
    actingInBook(User::factory()->premium()->create());

    Livewire::test(EditBookProfile::class)
        ->assertActionHidden('delete');
});

it('deletes an additional book with its receipts and returns to the default book', function () {
    Storage::fake(Transaction::RECEIPT_DISK);
    $user = User::factory()->premium()->create();
    $defaultBook = $user->books()->sole();
    $extraBook = Book::factory()->for($user)->create();
    $receiptPath = UploadedFile::fake()->image('struk.jpg')->store(Transaction::RECEIPT_DIRECTORY, Transaction::RECEIPT_DISK);
    Transaction::factory()->for($extraBook)->create(['receipt_photo_path' => $receiptPath]);
    actingInBook($user, $extraBook);

    Livewire::test(EditBookProfile::class)
        ->callAction('delete')
        ->assertRedirect("/app/{$defaultBook->id}");

    $this->assertModelMissing($extraBook);
    Storage::disk(Transaction::RECEIPT_DISK)->assertMissing($receiptPath);
});
