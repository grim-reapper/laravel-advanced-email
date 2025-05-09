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
        $tableName = config('advanced_email.database.tables.scheduled_emails', 'scheduled_emails');
        $connection = config('advanced_email.database.connection');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('retry_attempts')
                  ->references('id')->on(config('advanced_email.database.tables.scheduled_emails', 'scheduled_emails'))
                  ->onDelete('set null');
            $table->unsignedInteger('occurrence_number')->nullable()->after('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('advanced_email.database.tables.scheduled_emails', 'scheduled_emails');
        $connection = config('advanced_email.database.connection');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'occurrence_number']);
        });
    }
};