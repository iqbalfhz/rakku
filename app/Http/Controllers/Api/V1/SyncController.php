<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Support\Sync\LedgerSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu buku, dua arah: ponsel menarik perubahan server dan mendorong catatan yang dibuat offline.
 */
class SyncController extends Controller
{
    public function __construct(private LedgerSync $ledgerSync) {}

    public function show(Request $request, Book $book): JsonResponse
    {
        $this->authorizeBook($request, $book);

        $request->validate(['since' => ['nullable', 'date']]);

        $since = $request->filled('since') ? CarbonImmutable::parse($request->string('since')->toString()) : null;

        return response()->json($this->ledgerSync->pull($book, $since));
    }

    public function store(Request $request, Book $book): JsonResponse
    {
        $this->authorizeBook($request, $book);

        $data = $request->validate([
            'transactions' => ['present', 'array'],
            'transactions.*.public_id' => ['required', 'ulid'],
            'transactions.*.account_public_id' => ['required', 'ulid'],
            'transactions.*.category_public_id' => ['nullable', 'ulid'],
            'transactions.*.type' => ['required', 'in:income,expense'],
            'transactions.*.amount' => ['required', 'numeric', 'min:0'],
            'transactions.*.description' => ['nullable', 'string', 'max:255'],
            'transactions.*.transaction_date' => ['required', 'date'],
            'transactions.*.is_deleted' => ['boolean'],
            'transactions.*.updated_at' => ['required', 'date'],
        ]);

        $result = $this->ledgerSync->push($book, $data['transactions']);

        return response()->json($result + ['server_time' => now()->utc()->toIso8601ZuluString()]);
    }

    private function authorizeBook(Request $request, Book $book): void
    {
        abort_unless($book->user_id === $request->user()->id, 404);
    }
}
