<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\DebtType;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events sengaja tidak dimatikan karena buku default, saldo akun,
     * dan transaksi cicilan dibentuk oleh observer.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder demo tidak dijalankan di production. Buat admin dengan: php artisan app:make-admin {email}');

            return;
        }

        $admin = User::factory()->admin()->premium()->create([
            'name' => 'Admin RakKu',
            'email' => 'admin@example.com',
        ]);

        $this->seedDemoLedger($admin->books()->sole());

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    private function seedDemoLedger(Book $book): void
    {
        $accounts = collect([
            ['name' => 'Cash', 'type' => AccountType::Cash, 'initial_balance' => 500_000],
            ['name' => 'BCA', 'type' => AccountType::Bank, 'initial_balance' => 5_000_000],
            ['name' => 'GoPay', 'type' => AccountType::EWallet, 'initial_balance' => 200_000],
        ])->map(fn (array $account): Account => $book->accounts()->create($account));

        $categories = $book->categories()->get()->groupBy(fn ($category) => $category->type->value);

        foreach (range(1, 60) as $index) {
            $type = $index % 5 === 0 ? TransactionType::Income : TransactionType::Expense;

            $book->transactions()->create([
                'account_id' => $accounts->random()->id,
                'category_id' => $categories[$type->value]->random()->id,
                'type' => $type,
                'amount' => $type === TransactionType::Income
                    ? fake()->numberBetween(10, 50) * 100_000
                    : fake()->numberBetween(5, 300) * 1_000,
                'description' => fake()->optional()->sentence(3),
                'transaction_date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            ]);
        }

        $book->transfers()->create([
            'from_account_id' => $accounts[1]->id,
            'to_account_id' => $accounts[2]->id,
            'amount' => 150_000,
            'description' => 'Top up GoPay',
            'transfer_date' => today(),
        ]);

        $book->budgets()->create([
            'category_id' => $categories['expense']->firstWhere('name', 'Makan & Minum')->id,
            'amount' => 1_500_000,
            'alert_enabled' => true,
            'alert_threshold_percent' => 80,
        ]);

        $book->recurringTransactions()->create([
            'account_id' => $accounts[1]->id,
            'category_id' => $categories['expense']->firstWhere('name', 'Tagihan')->id,
            'type' => TransactionType::Expense,
            'amount' => 350_000,
            'description' => 'Tagihan internet',
            'frequency' => RecurringFrequency::Monthly,
            'start_date' => today()->addDays(5),
        ]);

        $debt = $book->debts()->create([
            'type' => DebtType::Receivable,
            'counterparty_name' => 'Budi',
            'amount' => 750_000,
            'due_date' => today()->addDays(3),
            'description' => 'Pinjam untuk servis motor',
        ]);

        $debt->payments()->create([
            'account_id' => $accounts[0]->id,
            'amount' => 250_000,
            'payment_date' => today(),
        ]);

        $client = $book->clients()->create([
            'name' => 'CV Maju Jaya',
            'email' => 'finance@majujaya.test',
        ]);

        $book->invoices()->create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-'.today()->year.'-0001',
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
        ])->items()->createMany([
            ['description' => 'Fotocopy A4', 'quantity' => 500, 'unit_price' => 300],
            ['description' => 'Jilid spiral', 'quantity' => 10, 'unit_price' => 7_500],
        ]);
    }
}
