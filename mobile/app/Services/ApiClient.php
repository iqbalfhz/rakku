<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Satu-satunya tempat aplikasi ponsel bicara ke server RakKu.
 */
class ApiClient
{
    /**
     * Sinyal di lapangan sering buruk, jadi tunggu secukupnya lalu menyerah dengan pesan jelas.
     */
    private const int TIMEOUT_SECONDS = 20;

    public function __construct(private TokenStore $tokenStore) {}

    /**
     * @return array{token: string, user: array{name: string, email: string}, books: list<array{public_id: string, name: string, is_default: bool}>}
     *
     * @throws RuntimeException saat kredensial ditolak atau server tidak terjangkau
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        $response = $this->request()->post('login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName,
        ]);

        if ($response->status() === 422) {
            throw new RuntimeException($response->json('message') ?? 'Email atau kata sandi salah.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Server sedang tidak bisa dihubungi. Coba lagi sebentar.');
        }

        return $response->json();
    }

    /**
     * @return array{server_time: string, accounts: list<array<string, mixed>>, categories: list<array<string, mixed>>, transactions: list<array<string, mixed>>}
     */
    public function pull(string $bookPublicId, ?string $since): array
    {
        $response = $this->authenticated()
            ->get("books/{$bookPublicId}/sync", array_filter(['since' => $since]))
            ->throw();

        return $response->json();
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return array{applied: int, skipped: int, server_time: string}
     */
    public function push(string $bookPublicId, array $transactions): array
    {
        $response = $this->authenticated()
            ->post("books/{$bookPublicId}/sync", ['transactions' => $transactions])
            ->throw();

        return $response->json();
    }

    public function logout(): void
    {
        try {
            $this->authenticated()->post('logout');
        } catch (ConnectionException) {
            // Tanpa sinyal pun pengguna tetap boleh keluar; token lokal dihapus oleh pemanggil.
        }
    }

    private function authenticated(): PendingRequest
    {
        return $this->request()->withToken($this->tokenStore->token() ?? '');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('rakku.server_url'), '/').'/api/v1/')
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }
}
