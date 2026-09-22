<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ID acak untuk URL buku (/app/{public_id}) agar jumlah buku tidak bisa ditebak dari angka berurutan.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
        });

        DB::table('books')->whereNull('public_id')->orderBy('id')->pluck('id')->each(
            fn (int $bookId) => DB::table('books')
                ->where('id', $bookId)
                ->update(['public_id' => Str::lower((string) Str::ulid())]),
        );

        Schema::table('books', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
