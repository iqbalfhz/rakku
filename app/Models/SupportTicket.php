<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * Nomor tiket diberikan otomatis agar pengguna dan admin menyebut tiket yang sama.
     */
    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->ticket_number ??= self::nextNumber();
        });
    }

    /**
     * Nomor berurutan per tahun, contoh: TKT-2026-0001.
     */
    public static function nextNumber(): string
    {
        $prefix = 'TKT-'.today()->year.'-';

        $lastNumber = static::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $sequence = $lastNumber === null ? 1 : (int) substr($lastNumber, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'last_message_at' => 'datetime',
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
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest();
    }

    public function isClosed(): bool
    {
        return $this->status === SupportTicketStatus::Closed;
    }

    public function close(): void
    {
        $this->status = SupportTicketStatus::Closed;
        $this->save();
    }

    /**
     * Tiket yang masih butuh perhatian admin.
     *
     * @param  Builder<SupportTicket>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', SupportTicketStatus::Open);
    }
}
