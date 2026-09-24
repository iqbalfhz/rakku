<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

/**
 * Menghapus akun dari dalam aplikasi ponsel.
 *
 * Google mewajibkan aplikasi yang punya pendaftaran akun menyediakan jalan ini
 * dari dalam aplikasi, bukan hanya lewat web. Pagarnya sama persis dengan yang di
 * panel: ketik ulang email, lalu kata sandi — supaya tidak ada yang kehilangan
 * seluruh catatannya karena satu ketukan yang salah.
 */
class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'email' => ['required', 'email', new In([$user->email])],
            'password' => ['required', 'string'],
        ], [
            'email.in' => 'Email tidak cocok dengan akun yang sedang masuk.',
        ]);

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi salah.']);
        }

        $user->tokens()->delete();

        app(DeleteAccount::class)->handle($user);

        return response()->json(['message' => 'Akun Anda sudah dihapus.']);
    }
}
