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

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('pending'); // pending, processing, sent, failed, cancelled
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('frequency')->nullable(); // null for one-time, or 'daily', 'weekly', 'monthly', etc.
            $table->json('frequency_options')->nullable(); // For storing specific recurrence rules
            $table->json('conditions')->nullable(); // For conditional sending logic
            $table->string('mailer')->nullable();
            $table->json('from')->nullable();
            $table->json('to');
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('subject')->nullable();
            $table->string('template_name')->nullable();
            $table->string('view')->nullable();
            $table->longText('html_content')->nullable();
            $table->json('view_data')->nullable();
            $table->json('placeholders')->nullable();
            $table->json('attachments')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('retry_attempts')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('advanced_email.database.tables.scheduled_emails', 'scheduled_emails');
        $connection = config('advanced_email.database.connection');

        Schema::connection($connection)->dropIfExists($tableName);
    }
};