<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiTrackingFieldsToTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('ai_summary_status', ['idle', 'queued', 'processing', 'done', 'failed'])
                ->default('idle')
                ->after('ai_suggested_reply');

            $table->enum('ai_reply_status', ['idle', 'queued', 'processing', 'done', 'failed'])
                ->default('idle')
                ->after('ai_summary_status');

            $table->enum('ai_priority_status', ['idle', 'queued', 'processing', 'done', 'failed'])
                ->default('idle')
                ->after('ai_reply_status');

            $table->text('ai_last_error')->nullable()->after('ai_priority_status');

            $table->timestamp('ai_last_run_at')->nullable()->after('ai_last_error');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary_status',
                'ai_reply_status',
                'ai_priority_status',
                'ai_last_error',
                'ai_last_run_at',
            ]);
        });
    }
}
