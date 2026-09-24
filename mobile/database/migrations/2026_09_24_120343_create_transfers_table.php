<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemindahan uang antar akun milik sendiri.
     *
     * Tabelnya terpisah dari transaksi karena memang bukan pemasukan dan bukan
     * pengeluaran — uangnya cuma berpindah tempat, dan laporan laba-rugi tidak
     * boleh ikut menghitungnya.
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('from_account_public_id')->index();
            $table->string('to_account_public_id');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->date('transfer_date')->index();
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
        Schema::dropIfExists('transfers');
    }
};
