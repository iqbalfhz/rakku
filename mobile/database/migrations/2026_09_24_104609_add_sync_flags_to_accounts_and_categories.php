<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun dan kategori kini bisa dibuat dan diubah dari ponsel, jadi keduanya perlu
     * penanda "belum terkirim" seperti tabel lain.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->boolean('has_activity')->default(false);
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['initial_balance', 'has_activity', 'is_dirty', 'is_deleted']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['is_dirty', 'is_deleted']);
        });
    }
};
