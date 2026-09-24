<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ID acak supaya ponsel bisa membuat anggaran saat offline tanpa bentrok dengan server.
     * Penanda hapus tidak diperlukan: daftar anggaran selalu dikirim utuh, jadi yang
     * hilang dari daftar memang sudah tidak ada.
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable()->after('id');
        });

        DB::table('budgets')->whereNull('public_id')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('budgets')
                ->where('id', $id)
                ->update(['public_id' => Str::lower((string) Str::ulid())]),
        );

        Schema::table('budgets', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['public_id']);
            $blueprint->dropColumn('public_id');
        });
    }
};
