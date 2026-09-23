<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor tiket agar pengguna dan admin bisa menyebut tiket yang sama tanpa salah paham.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->after('id');
        });

        DB::table('support_tickets')->whereNull('ticket_number')->orderBy('id')->pluck('created_at', 'id')->each(
            fn (?string $createdAt, int $ticketId) => DB::table('support_tickets')
                ->where('id', $ticketId)
                ->update(['ticket_number' => 'TKT-'.substr((string) $createdAt, 0, 4).'-'.str_pad((string) $ticketId, 4, '0', STR_PAD_LEFT)]),
        );

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('ticket_number')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
