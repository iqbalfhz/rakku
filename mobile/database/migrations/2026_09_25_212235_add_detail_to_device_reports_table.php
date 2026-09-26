<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keterangan panjang kerusakan: berkas, baris, dan jejak tumpukannya.
     *
     * Selama ini yang terkirim hanya kalimat singkat exception. "Undefined array
     * key PHP_SELF" memberi tahu apa yang pecah, tapi tidak memberi tahu di mana —
     * dan tanpa itu kerusakan di ponsel orang lain hampir mustahil ditelusuri.
     */
    public function up(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->text('detail')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
    }
};
