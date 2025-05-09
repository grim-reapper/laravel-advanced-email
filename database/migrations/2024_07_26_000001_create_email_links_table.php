<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $logTable = Config::get('advanced_email.database.tables.email_logs', 'email_logs');
        $linkTable = Config::get('advanced_email.database.tables.email_links', 'email_links');
        $connection = Config::get('advanced_email.database.connection');

        if (!Schema::connection($connection)->hasTable($linkTable)) {
            Schema::connection($connection)->create($linkTable, function (Blueprint $table) use ($logTable) {
                $table->id();
                $table->uuid('uuid')->unique(); // Unique identifier for the link
                $table->unsignedBigInteger('email_log_id');
                $table->text('original_url');
                $table->timestamp('clicked_at')->nullable();
                $table->timestamps();

                // Assuming email_logs table uses bigIncrements for id
                $table->foreign('email_log_id')
                      ->references('id')->on($logTable)
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $linkTable = Config::get('advanced_email.database.tables.email_links', 'email_links');
        $connection = Config::get('advanced_email.database.connection');

        Schema::connection($connection)->dropIfExists($linkTable);
    }
};