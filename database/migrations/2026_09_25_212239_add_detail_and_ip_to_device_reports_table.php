<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua hal yang membuat laporan kerusakan bisa ditelusuri.
     *
     * `detail` memuat berkas, baris, dan jejak tumpukan — tanpa itu pesan seperti
     * "Undefined array key PHP_SELF" hanya memberi tahu apa yang pecah, bukan di mana.
     *
     * `ip_address` dicatat server dari permintaannya sendiri, bukan dikirim ponsel:
     * apa pun yang dilaporkan aplikasi tentang dirinya bisa keliru atau dipalsukan,
     * sedangkan alamat asal permintaan tidak.
     */
    public function up(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->longText('detail')->nullable()->after('message');
            $table->string('ip_address', 45)->nullable()->after('context');
        });
    }

    public function down(): void
    {
        Schema::table('device_reports', function (Blueprint $table) {
            $table->dropColumn(['detail', 'ip_address']);
        });
    }
};
