<?php

namespace App\Services;

use App\Models\Budget;
use Illuminate\Support\Str;

/**
 * Menulis anggaran di dalam ponsel, tanpa perlu sinyal.
 */
class BudgetWriter
{
    /**
     * @param  array{category_public_id: string, amount: float, alert_enabled?: bool, alert_threshold_percent?: int|null}  $data
     */
    public function record(array $data): Budget
    {
        return Budget::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            ...$this->attributes($data),
            'is_dirty' => true,
            'is_deleted' => false,
        ]);
    }

    /**
     * @param  array{category_public_id: string, amount: float, alert_enabled?: bool, alert_threshold_percent?: int|null}  $data
     */
    public function revise(Budget $budget, array $data): Budget
    {
        $budget->update([...$this->attributes($data), 'is_dirty' => true]);

        return $budget;
    }

    /**
     * Penghapusan disimpan dulu sebagai penanda, karena server perlu diberi tahu.
     */
    public function remove(Budget $budget): void
    {
        $budget->update(['is_deleted' => true, 'is_dirty' => true]);
    }

    /**
     * @param  array{category_public_id: string, amount: float, alert_enabled?: bool, alert_threshold_percent?: int|null}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $alertEnabled = $data['alert_enabled'] ?? false;

        return [
            'category_public_id' => $data['category_public_id'],
            'amount' => $data['amount'],
            'alert_enabled' => $alertEnabled,
            'alert_threshold_percent' => $alertEnabled
                ? ($data['alert_threshold_percent'] ?? Budget::DEFAULT_ALERT_THRESHOLD)
                : null,
        ];
    }
}
