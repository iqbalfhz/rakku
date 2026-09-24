<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tabel utang-piutang yang ikut disinkronkan ke aplikasi ponsel.
     *
     * @var list<string>
     */
    private const array TABLES = ['debts', 'debt_payments'];

    /**
     * Sama seperti buku kas: ID acak agar ponsel bisa mencatat saat offline, dan penanda
     * hapus agar utang yang dibatalkan di web ikut hilang dari ponsel.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->ulid('public_id')->nullable()->after('id');
                $blueprint->softDeletes();
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

        Schema::table('debts', function (Blueprint $blueprint) {
            $blueprint->index(['book_id', 'updated_at']);
        });

        Schema::table('debt_payments', function (Blueprint $blueprint) {
            $blueprint->index(['debt_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debts', function (Blueprint $blueprint) {
            $blueprint->dropIndex(['book_id', 'updated_at']);
        });

        Schema::table('debt_payments', function (Blueprint $blueprint) {
            $blueprint->dropIndex(['debt_id', 'updated_at']);
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['public_id']);
                $blueprint->dropColumn('public_id');
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
