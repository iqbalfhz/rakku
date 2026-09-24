<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto struk diunggah terpisah dari sinkronisasi teks, supaya catatan tidak
 * tersandera koneksi lambat: angkanya sampai duluan, fotonya menyusul.
 */
class ReceiptController extends Controller
{
    /**
     * Ukuran maksimal foto struk dalam kilobyte.
     */
    private const int MAX_SIZE_KB = 5120;

    /**
     * Kirimkan kembali foto struknya, supaya ponsel yang baru dipasang tetap
     * bisa menampilkan bukti transaksi lama.
     */
    public function show(Request $request, Book $book, string $transactionPublicId): StreamedResponse
    {
        abort_unless($book->user_id === $request->user()->id, 404);

        $transaction = $book->transactions()->where('public_id', $transactionPublicId)->firstOrFail();

        abort_if($transaction->receipt_photo_path === null, 404);

        return Storage::disk(Transaction::receiptDisk())->download($transaction->receipt_photo_path);
    }

    public function store(Request $request, Book $book, string $transactionPublicId): JsonResponse
    {
        abort_unless($book->user_id === $request->user()->id, 404);

        $request->validate([
            'receipt' => ['required', 'image', 'max:'.self::MAX_SIZE_KB],
        ]);

        $transaction = $book->transactions()->where('public_id', $transactionPublicId)->firstOrFail();

        $path = $request->file('receipt')->store(
            Transaction::RECEIPT_DIRECTORY,
            Transaction::receiptDisk(),
        );

        // Observer transaksi menghapus foto lama begitu kolomnya berubah.
        $transaction->update(['receipt_photo_path' => $path]);

        return response()->json([
            'has_receipt' => true,
            'updated_at' => $transaction->fresh()->updated_at->utc()->toIso8601ZuluString(),
        ]);
    }
}
