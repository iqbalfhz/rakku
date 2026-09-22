<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;

class BookObserver
{
    public function created(Book $book): void
    {
        $book->seedDefaultCategories();
    }

    /**
     * Data turunan terhapus lewat cascade database, jadi file struknya dibersihkan di sini.
     */
    public function deleting(Book $book): void
    {
        $receiptPaths = $book->transactions()->whereNotNull('receipt_photo_path')->pluck('receipt_photo_path');

        Storage::disk(Transaction::RECEIPT_DISK)->delete($receiptPaths->all());
    }
}
