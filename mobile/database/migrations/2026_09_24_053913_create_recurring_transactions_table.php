<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan jadwal transaksi berulang di dalam ponsel.
     *
     * next_run_date hanya untuk ditampilkan; yang menghitung dan menjalankannya
     * tetap penjadwal di server, karena ponsel yang tertutup tidak bangun sendiri.
     */
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('account_public_id')->index();
            $table->string('category_public_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('frequency');
            $table->date('start_date');
            $table->date('next_run_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('server_updated_at')->nullable();
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
