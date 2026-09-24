<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan klien dan invoice di dalam ponsel.
     *
     * invoice_payments hanya kotak keluar: server tidak punya tabel ini, isinya
     * pelunasan yang menunggu dikirim lalu dibuang setelah sampai.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('client_public_id')->index();
            $table->string('invoice_number')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->string('notes')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('server_updated_at')->nullable();
            $table->boolean('is_dirty')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('invoice_public_id')->index();
            $table->string('description');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_public_id')->index();
            $table->string('account_public_id');
            $table->string('transaction_public_id')->nullable();
            $table->date('paid_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('clients');
    }
};
