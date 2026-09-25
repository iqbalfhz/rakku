<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Perangkat mana saja yang masih bisa membuka akun ini.
 *
 * Token sengaja tidak diberi masa berlaku: aplikasi ini dipakai tanpa sinyal, dan
 * token yang mati di tengah lapangan berarti pengguna tidak bisa membuka bukunya
 * sendiri. Gantinya, akses bisa dicabut kapan saja dari sini — itu yang sebenarnya
 * dibutuhkan saat ponsel hilang.
 */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        $devices = $request->user()->tokens()
            ->latest('last_used_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentId,
                'last_used_at' => $token->last_used_at?->utc()->toIso8601ZuluString(),
                'signed_in_at' => $token->created_at->utc()->toIso8601ZuluString(),
            ]);

        return response()->json(['devices' => $devices->all()]);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->first();

        abort_if($token === null, 404);

        // Mencabut akses perangkat ini sendiri sama saja dengan keluar; lakukan itu
        // lewat tombol Keluar supaya salinan buku di ponsel ikut dibersihkan.
        abort_if($token->id === $request->user()->currentAccessToken()?->id, 422, 'Gunakan tombol Keluar untuk perangkat ini.');

        $token->delete();

        return response()->json(['message' => 'Akses perangkat itu dicabut.']);
    }
}
