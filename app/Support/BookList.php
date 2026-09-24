<?php

namespace App\Support;

use App\Models\Book;
use App\Models\User;

/**
 * Daftar buku seperti yang dilihat aplikasi ponsel.
 *
 * Ditulis di satu tempat karena dikirim dari dua jalur — saat masuk dan saat buku
 * baru dibuat — dan ponsel menyimpannya mentah-mentah untuk dipakai tanpa sinyal.
 */
final class BookList
{
    /**
     * @return list<array{public_id: string, name: string, is_default: bool}>
     */
    public static function forUser(User $user): array
    {
        return $user->books()->orderBy('name')->get()->map(fn (Book $book): array => [
            'public_id' => $book->public_id,
            'name' => $book->name,
            'is_default' => (bool) $book->is_default,
        ])->all();
    }
}
