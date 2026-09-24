<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\BookList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Membuat buku tidak bisa dikerjakan offline: buku baru butuh identitas dari server
 * sebelum apa pun boleh dicatat ke dalamnya.
 */
class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['books' => BookList::forUser($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        abort_unless(
            $request->user()->canCreateBook(),
            403,
            'Buku tambahan hanya untuk pelanggan premium.',
        );

        $book = $request->user()->books()->create(['name' => $data['name']]);

        return response()->json([
            'book' => ['public_id' => $book->public_id, 'name' => $book->name, 'is_default' => (bool) $book->is_default],
            'books' => BookList::forUser($request->user()->refresh()),
        ], 201);
    }
}
