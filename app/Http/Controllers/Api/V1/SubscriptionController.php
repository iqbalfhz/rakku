<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitSubscriptionPayment;
use App\Enums\SubscriptionPackage;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\SubscriptionConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Berlangganan dari ponsel: lihat harga dan rekening tujuan, lalu kirim bukti transfer.
 *
 * Semuanya butuh sinyal. Itu wajar — transfernya sendiri juga baru saja dilakukan online.
 */
class SubscriptionController extends Controller
{
    /**
     * Berapa banyak riwayat pembayaran yang ikut dikirim ke ponsel.
     */
    private const int HISTORY_LIMIT = 10;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'plan' => [
                'is_premium' => $user->isPremium(),
                'expires_at' => $this->expiryOf($user),
            ],
            'bank' => SubscriptionConfig::bank(),
            'packages' => collect(SubscriptionPackage::cases())->map(fn (SubscriptionPackage $package): array => [
                'value' => $package->value,
                'months' => $package->months(),
                'price' => $package->price(),
            ])->all(),
            'pending_payment' => $this->paymentPayload($user->subscriptionPayments()->pending()->latest()->first()),
            'payments' => $user->subscriptionPayments()
                ->latest()
                ->limit(self::HISTORY_LIMIT)
                ->get()
                ->map($this->paymentPayload(...))
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'package' => ['required', Rule::enum(SubscriptionPackage::class)],
            'proof' => ['required', 'image', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Satu pengajuan menunggu pada satu waktu: sinyal buruk membuat orang menekan kirim berkali-kali.
        abort_if(
            $user->subscriptionPayments()->pending()->exists(),
            409,
            'Masih ada pengajuan yang menunggu diverifikasi admin.',
        );

        $proofPath = $request->file('proof')->store(
            SubscriptionPayment::PROOF_DIRECTORY,
            ['disk' => SubscriptionPayment::proofDisk(), 'visibility' => 'private'],
        );

        $payment = app(SubmitSubscriptionPayment::class)->handle(
            $user,
            SubscriptionPackage::from($data['package']),
            $proofPath,
            $data['note'] ?? null,
        );

        return response()->json(['payment' => $this->paymentPayload($payment)], 201);
    }

    private function expiryOf(User $user): ?string
    {
        $subscription = $user->currentSubscription;

        return $subscription?->isActivePremium() && $subscription->expires_at !== null
            ? $subscription->expires_at->utc()->toIso8601ZuluString()
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentPayload(?SubscriptionPayment $payment): ?array
    {
        if ($payment === null) {
            return null;
        }

        return [
            'package' => $payment->package->value,
            'months' => $payment->package->months(),
            'amount' => (float) $payment->amount,
            'status' => $payment->status->value,
            'status_label' => $payment->status->getLabel(),
            'note' => $payment->note,
            'submitted_at' => $payment->created_at->utc()->toIso8601ZuluString(),
            'reviewed_at' => $payment->reviewed_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
