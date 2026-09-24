<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

#[Fillable(['client_id', 'invoice_number', 'issue_date', 'due_date', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const int SHARED_PDF_LINK_DAYS = 30;

    /**
     * Semua URL invoice memakai public_id, bukan id berurutan.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Sertakan kolom `total_amount` hasil penjumlahan (quantity x unit_price) semua item.
     *
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function withTotalAmount(Builder $query): void
    {
        $query->withSum('items as total_amount', DB::raw('quantity * unit_price'));
    }

    public function totalAmount(): float
    {
        return (float) ($this->total_amount ?? $this->items->sum(fn (InvoiceItem $item): float => $item->subtotal()));
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function markAsSent(): void
    {
        $this->status = $this->due_date->lt(today()) ? InvoiceStatus::Overdue : InvoiceStatus::Sent;
        $this->save();
    }

    /**
     * Pengiriman pertama mengubah draft menjadi terkirim; kirim ulang tidak mengubah status.
     */
    public function markAsSentIfDraft(): void
    {
        if ($this->status === InvoiceStatus::Draft) {
            $this->markAsSent();
        }
    }

    /**
     * Link PDF bertanda tangan yang bisa dibuka klien tanpa login.
     */
    public function sharedPdfUrl(): string
    {
        return URL::temporarySignedRoute(
            'invoices.shared-pdf',
            now()->addDays(self::SHARED_PDF_LINK_DAYS),
            ['invoice' => $this],
        );
    }

    /**
     * Nomor invoice berurutan per buku per tahun, contoh: INV-2026-0001.
     */
    public static function nextNumberFor(Book $book): string
    {
        $prefix = 'INV-'.today()->year.'-';

        $lastNumber = $book->invoices()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $lastNumber === null ? 1 : (int) substr($lastNumber, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
