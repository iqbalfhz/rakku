<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ID acak untuk URL invoice agar klien tidak bisa menebak jumlah invoice dari angka berurutan.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
        });

        DB::table('invoices')->whereNull('public_id')->orderBy('id')->pluck('id')->each(
            fn (int $invoiceId) => DB::table('invoices')
                ->where('id', $invoiceId)
                ->update(['public_id' => Str::lower((string) Str::ulid())]),
        );

        Schema::table('invoices', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
