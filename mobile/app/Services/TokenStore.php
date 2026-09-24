<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Menyimpan hasil login di dalam ponsel supaya pengguna tidak diminta masuk berulang kali.
 */
class TokenStore
{
    private const string TOKEN = 'auth.token';

    private const string USER_NAME = 'auth.user_name';

    private const string BOOK_ID = 'auth.book_public_id';

    private const string BOOK_NAME = 'auth.book_name';

    private const string BOOKS = 'auth.books';

    private const string LAST_SYNCED_AT = 'sync.last_synced_at';

    private const string LAST_ATTEMPT_AT = 'sync.last_attempt_at';

    private const string LAST_ATTEMPT_FAILED = 'sync.last_attempt_failed';

    public function token(): ?string
    {
        return Setting::read(self::TOKEN);
    }

    public function isSignedIn(): bool
    {
        return $this->token() !== null;
    }

    public function userName(): ?string
    {
        return Setting::read(self::USER_NAME);
    }

    public function bookPublicId(): ?string
    {
        return Setting::read(self::BOOK_ID);
    }

    public function bookName(): ?string
    {
        return Setting::read(self::BOOK_NAME);
    }

    /**
     * Semua buku milik pengguna, disalin saat masuk supaya bisa berpindah tanpa sinyal.
     *
     * @return list<array{public_id: string, name: string, is_default: bool}>
     */
    public function books(): array
    {
        return json_decode(Setting::read(self::BOOKS, '[]'), associative: true) ?: [];
    }

    /**
     * @param  list<array{public_id: string, name: string, is_default: bool}>  $books
     */
    public function rememberBooks(array $books): void
    {
        Setting::write(self::BOOKS, json_encode(array_values($books)));
    }

    public function lastSyncedAt(): ?string
    {
        return Setting::read(self::LAST_SYNCED_AT);
    }

    /**
     * Kapan terakhir kali ponsel *mencoba* menyambung, berhasil atau tidak.
     * Dipakai untuk menahan percobaan otomatis supaya tidak beruntun.
     */
    public function lastSyncAttemptAt(): ?string
    {
        return Setting::read(self::LAST_ATTEMPT_AT);
    }

    public function lastSyncAttemptFailed(): bool
    {
        return Setting::read(self::LAST_ATTEMPT_FAILED) === '1';
    }

    public function rememberSyncAttempt(bool $succeeded): void
    {
        Setting::write(self::LAST_ATTEMPT_AT, now()->toIso8601String());
        Setting::write(self::LAST_ATTEMPT_FAILED, $succeeded ? '0' : '1');
    }

    public function rememberSession(string $token, string $userName): void
    {
        Setting::write(self::TOKEN, $token);
        Setting::write(self::USER_NAME, $userName);
    }

    public function rememberBook(string $publicId, string $name): void
    {
        Setting::write(self::BOOK_ID, $publicId);
        Setting::write(self::BOOK_NAME, $name);
    }

    public function forgetSync(): void
    {
        Setting::forget(self::LAST_SYNCED_AT);
        Setting::forget(self::LAST_ATTEMPT_AT);
        Setting::forget(self::LAST_ATTEMPT_FAILED);
    }

    public function rememberSync(string $serverTime): void
    {
        Setting::write(self::LAST_SYNCED_AT, $serverTime);
    }

    /**
     * Keluar dari akun. Data buku ikut dilupakan supaya tidak tercampur
     * kalau ponsel yang sama dipakai akun lain.
     */
    public function forget(): void
    {
        collect([self::TOKEN, self::USER_NAME, self::BOOK_ID, self::BOOK_NAME, self::BOOKS, self::LAST_SYNCED_AT, self::LAST_ATTEMPT_AT, self::LAST_ATTEMPT_FAILED])
            ->each(fn (string $key) => Setting::forget($key));
    }
}
