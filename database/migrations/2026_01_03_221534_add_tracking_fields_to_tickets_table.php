<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')->index();
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at')->index();
            $table->unsignedBigInteger('reopened_by')->nullable()->after('closed_by')->index();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('closed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('reopened_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['updated_by', 'closed_by', 'reopened_by']);
        });
    }
};
