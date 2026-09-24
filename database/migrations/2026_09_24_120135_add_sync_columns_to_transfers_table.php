<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ID acak supaya pemindahan uang bisa dicatat dari ponsel saat offline, dan
     * penanda hapus supaya pembatalannya ikut sampai ke ponsel.
     */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable()->after('id');
            $blueprint->softDeletes();
        });

        DB::table('transfers')->whereNull('public_id')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('transfers')
                ->where('id', $id)
                ->update(['public_id' => Str::lower((string) Str::ulid())]),
        );

        Schema::table('transfers', function (Blueprint $blueprint) {
            $blueprint->ulid('public_id')->nullable(false)->unique()->change();
            $blueprint->index(['book_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $blueprint) {
            $blueprint->dropIndex(['book_id', 'updated_at']);
            $blueprint->dropUnique(['public_id']);
            $blueprint->dropColumn('public_id');
            $blueprint->dropSoftDeletes();
        });
    }
};
