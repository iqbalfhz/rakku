<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Observers\BookObserver;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_default'])]
#[ObservedBy(BookObserver::class)]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    public const string DEFAULT_NAME = 'Pribadi';

    /**
     * Kategori bawaan yang dibuat otomatis untuk setiap buku baru.
     *
     * @var array<string, list<string>>
     */
    public const array DEFAULT_CATEGORIES = [
        'income' => ['Gaji', 'Bonus', 'Penjualan', 'Pemasukan Lainnya'],
        'expense' => ['Makan & Minum', 'Transportasi', 'Belanja', 'Tagihan', 'Hiburan', 'Kesehatan', 'Pendidikan', 'Pengeluaran Lainnya'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * @return HasMany<RecurringTransaction, $this>
     */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * @return HasMany<Debt, $this>
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function seedDefaultCategories(): void
    {
        foreach (self::DEFAULT_CATEGORIES as $type => $names) {
            foreach ($names as $name) {
                $this->categories()->create([
                    'name' => $name,
                    'type' => TransactionType::from($type),
                ]);
            }
        }
    }
}
