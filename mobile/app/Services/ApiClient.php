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
     * @param  array<string, list<array<string, mixed>>>  $changes
     * @return array{applied: int, skipped: int, server_time: string}
     */
    public function push(string $bookPublicId, array $changes): array
    {
        $response = $this->authenticated()
            ->post("books/{$bookPublicId}/sync", $changes)
            ->throw();

        return $response->json();
    }

    /**
     * Minta link PDF bertanda tangan beserta pesan pengantarnya, untuk dibagikan
     * lewat aplikasi apa pun yang ada di ponsel.
     *
     * @return array{invoice_number: string, url: string, message: string, status: string}
     */
    public function shareInvoice(string $bookPublicId, string $invoicePublicId): array
    {
        $response = $this->authenticated()
            ->post("books/{$bookPublicId}/invoices/{$invoicePublicId}/share")
            ->throw();

        return $response->json();
    }

    /**
     * Unggah foto struk. Dipisah dari sinkronisasi teks supaya catatan tidak
     * tersandera koneksi lambat: angkanya sampai duluan, fotonya menyusul.
     */
    public function uploadReceipt(string $bookPublicId, string $transactionPublicId, string $absolutePath): void
    {
        $this->authenticated()
            ->attach('receipt', file_get_contents($absolutePath), basename($absolutePath))
            ->post("books/{$bookPublicId}/transactions/{$transactionPublicId}/receipt")
            ->throw();
    }

    /**
     * Ambil foto struk dari server untuk catatan lama yang fotonya tidak ada di ponsel ini.
     */
    public function downloadReceipt(string $bookPublicId, string $transactionPublicId): string
    {
        return $this->authenticated()
            ->get("books/{$bookPublicId}/transactions/{$transactionPublicId}/receipt")
            ->throw()
            ->body();
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
