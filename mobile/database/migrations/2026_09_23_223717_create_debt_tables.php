<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan utang-piutang di dalam ponsel. Cicilan hanya bisa ditambah dari sini,
     * jadi tabelnya tidak perlu penanda hapus seperti transaksi.
     */
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('type');
            $table->string('counterparty_name');
            $table->decimal('amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date')->nullable()->index();
            $table->string('description')->nullable();
            $table->string('status')->default('unpaid');
            $table->boolean('reminder_enabled')->default(true);
            $table->timestamp('server_updated_at')->nullable();
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('debt_public_id')->index();
            $table->string('account_public_id');
            $table->string('transaction_public_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('notes')->nullable();
            $table->timestamp('server_updated_at')->nullable();
            $table->boolean('is_dirty')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('debts');
    }
};
