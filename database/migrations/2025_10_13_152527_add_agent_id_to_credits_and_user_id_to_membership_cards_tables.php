<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajout du user_id (agent responsable) dans membership_cards
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('member_id')
                ->constrained('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('membership_cards', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
