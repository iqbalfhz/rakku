<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan anggaran di dalam ponsel. Satu kategori satu anggaran, sama seperti di server.
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('category_public_id')->index();
            $table->decimal('amount', 15, 2);
            $table->boolean('alert_enabled')->default(false);
            $table->unsignedTinyInteger('alert_threshold_percent')->nullable();
            $table->timestamp('server_updated_at')->nullable();
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
