<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keadaan ponsel pada saat kerusakan terjadi, bukan pada saat laporannya dikirim.
     *
     * Keduanya biasanya berjarak beberapa detik, tapi laporan menunggu sinyal dan
     * sinyal bisa hilang berhari-hari. Pengguna yang crash di versi 1.0.0, lalu
     * memperbarui aplikasi, lalu baru tersambung, akan mengirim laporan yang
     * mengaku berasal dari versi 1.1.0 — persis menyesatkan pada pertanyaan yang
     * paling ingin dijawab: versi mana yang rusak.
     */
    public function up(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->json('context')->nullable()->after('detail');
        });
    }

    public function down(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
