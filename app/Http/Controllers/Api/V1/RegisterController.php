<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Mendaftar dari aplikasi ponsel.
 *
 * Tidak ada token yang dikembalikan di sini: email harus diverifikasi dulu, aturan
 * yang sama seperti di web (lihat LoginController). Mengembalikan token sebelum itu
 * berarti membuat jalan pintas yang membuat verifikasi email jadi sia-sia.
 */
class RegisterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Masuk saja dengan email itu.',
        ]);

        // Plan gratis dan buku pertama dibuatkan UserObserver, sama seperti pendaftaran di web.
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        return response()->json([
            'message' => 'Akun dibuat. Buka email Anda dan klik tautan verifikasinya, lalu masuk dari sini.',
            'email' => $user->email,
        ], 201);
    }
}
