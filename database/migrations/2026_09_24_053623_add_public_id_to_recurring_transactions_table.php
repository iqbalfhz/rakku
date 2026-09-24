<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ID acak supaya jadwal bisa diatur dari ponsel saat offline. Penanda hapus tidak
     * diperlukan: daftar jadwal selalu dikirim utuh, jadi yang hilang memang sudah tidak ada.
     */
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable()->after('id');
        });

        DB::table('recurring_transactions')->whereNull('public_id')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('recurring_transactions')
                ->where('id', $id)
                ->update(['public_id' => Str::lower((string) Str::ulid())]),
        );

        Schema::table('recurring_transactions', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['public_id']);
            $blueprint->dropColumn('public_id');
        });
    }
};
