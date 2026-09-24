<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kotak keluar laporan kerusakan.
     *
     * Kerusakan paling sering terjadi justru saat sinyal buruk, jadi laporannya
     * disimpan dulu di sini dan dikirim saat ada kesempatan.
     */
    public function up(): void
    {
        Schema::create('device_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->text('message');
            $table->string('fingerprint')->index();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_reports');
    }
};
