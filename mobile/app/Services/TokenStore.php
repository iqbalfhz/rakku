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

    private const string LAST_SYNCED_AT = 'sync.last_synced_at';

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

    public function lastSyncedAt(): ?string
    {
        return Setting::read(self::LAST_SYNCED_AT);
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
        collect([self::TOKEN, self::USER_NAME, self::BOOK_ID, self::BOOK_NAME, self::LAST_SYNCED_AT])
            ->each(fn (string $key) => Setting::forget($key));
    }
}
