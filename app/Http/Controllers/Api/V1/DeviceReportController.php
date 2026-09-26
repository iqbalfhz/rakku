<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceReportKind;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\DeviceReportReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Pintu bagi aplikasi ponsel untuk mengabarkan kerusakannya sendiri.
 *
 * Tanpa ini, aplikasi yang rusak di ponsel seseorang tidak meninggalkan jejak
 * apa pun: penggunanya diam saja lalu berhenti memakai aplikasi.
 */
class DeviceReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reports' => ['present', 'array', 'max:20'],
            'reports.*.kind' => ['required', Rule::enum(DeviceReportKind::class)],
            'reports.*.message' => ['required', 'string', 'max:2000'],
            'reports.*.detail' => ['nullable', 'string', 'max:8000'],
            'reports.*.context' => ['nullable', 'array'],
            'reports.*.occurred_at' => ['required', 'date'],
        ]);

        foreach ($data['reports'] as $payload) {
            $this->record($request->user(), $payload, $request->ip());
        }

        return response()->json(['received' => count($data['reports'])]);
    }

    /**
     * Alamat IP diambil dari permintaannya sendiri, bukan dari isi laporan.
     * Apa pun yang aplikasi katakan tentang dirinya bisa keliru atau dipalsukan;
     * alamat asal permintaan tidak.
     *
     * @param  array<string, mixed>  $payload
     */
    private function record(User $user, array $payload, ?string $ipAddress): void
    {
        $kind = DeviceReportKind::from($payload['kind']);

        $report = $user->deviceReports()->create([
            'kind' => $kind,
            'message' => $payload['message'],
            'detail' => $payload['detail'] ?? null,
            'context' => $payload['context'] ?? null,
            'ip_address' => $ipAddress,
            'occurred_at' => $payload['occurred_at'],
        ]);

        if (! $kind->deservesAdminNotice()) {
            return;
        }

        Notification::send(User::query()->where('is_admin', true)->get(), new DeviceReportReceived($report));
    }
}
