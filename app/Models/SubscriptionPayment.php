<?php

namespace App\Models;

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPaymentStatus;
use Database\Factories\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['package', 'amount', 'proof_path', 'note'])]
class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory;

    public const string PROOF_DIRECTORY = 'payment-proofs';

    public static function proofDisk(): string
    {
        return config('filesystems.payment_proofs');
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package' => SubscriptionPackage::class,
            'status' => SubscriptionPaymentStatus::class,
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionPaymentStatus::Pending;
    }

    /**
     * @param  Builder<SubscriptionPayment>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', SubscriptionPaymentStatus::Pending);
    }
}
