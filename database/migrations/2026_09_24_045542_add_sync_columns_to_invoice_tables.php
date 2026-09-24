<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Klien dan baris invoice ikut disinkronkan, jadi keduanya butuh ID acak.
     *
     * @var list<string>
     */
    private const array TABLES = ['clients', 'invoice_items'];

    /**
     * Invoice sendiri sudah punya public_id sejak link PDF dibuat; yang kurang
     * hanyalah penanda hapus supaya invoice yang dibatalkan ikut hilang dari ponsel.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->ulid('public_id')->nullable()->after('id');
            });

            DB::table($table)->whereNull('public_id')->orderBy('id')->pluck('id')->each(
                fn (int $id) => DB::table($table)
                    ->where('id', $id)
                    ->update(['public_id' => Str::lower((string) Str::ulid())]),
            );

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->ulid('public_id')->nullable(false)->unique()->change();
            });
        }

        Schema::table('invoices', function (Blueprint $blueprint) {
            $blueprint->softDeletes();
            $blueprint->index(['book_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $blueprint) {
            $blueprint->dropIndex(['book_id', 'updated_at']);
            $blueprint->dropSoftDeletes();
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['public_id']);
                $blueprint->dropColumn('public_id');
            });
        }
    }
};
