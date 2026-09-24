<?php

namespace App\Actions;

use App\Enums\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentSubmitted;
use Illuminate\Support\Facades\Notification;

/**
 * Catat pengajuan pembayaran premium lalu beri tahu admin agar segera diverifikasi.
 *
 * Dipakai dua jalur — halaman langganan di web dan aplikasi ponsel — supaya tidak ada
 * pengajuan yang lolos tanpa sampai ke lonceng admin.
 */
class SubmitSubscriptionPayment
{
    public function handle(User $user, SubscriptionPackage $package, string $proofPath, ?string $note = null): SubscriptionPayment
    {
        $payment = $user->subscriptionPayments()->create([
            'package' => $package,
            'amount' => $package->price(),
            'proof_path' => $proofPath,
            'note' => $note,
        ]);

        Notification::send(User::query()->where('is_admin', true)->get(), new SubscriptionPaymentSubmitted($payment));

        return $payment;
    }
}
