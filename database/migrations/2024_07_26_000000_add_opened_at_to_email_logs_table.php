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
        $tableName = config('advanced_email.database.tables.email_logs', 'email_logs');
        $connection = config('advanced_email.database.connection');

        if (Schema::connection($connection)->hasTable($tableName) && !Schema::connection($connection)->hasColumn($tableName, 'opened_at')) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) {
                $table->timestamp('opened_at')->nullable()->after('error');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('advanced_email.database.tables.email_logs', 'email_logs');
        $connection = config('advanced_email.database.connection');

        if (Schema::connection($connection)->hasTable($tableName) && Schema::connection($connection)->hasColumn($tableName, 'opened_at')) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table) {
                $table->dropColumn('opened_at');
            });
        }
    }
};