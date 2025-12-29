<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status')->default('open')->index(); // open|in_progress|waiting|resolved|closed
            $table->unsignedBigInteger('assigned_to')->nullable()->index(); // user_id (futuro)
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['status', 'assigned_to', 'closed_at']);
        });
    }
};