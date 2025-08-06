<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('email_template_versions', function (Blueprint $table) {
            // Sender configuration
            $table->string('from_email', 255)->nullable()->after('is_active')->comment('Sender email address for this template version');
            $table->string('from_name', 255)->nullable()->after('from_email')->comment('Sender name for this template version');
            
            // Recipient configuration - using TEXT to support both comma-separated and JSON formats
            $table->text('to_email')->nullable()->after('from_name')->comment('Default recipient email addresses (JSON array or comma-separated)');
            $table->text('cc_email')->nullable()->after('to_email')->comment('Default CC email addresses (JSON array or comma-separated)');
            $table->text('bcc_email')->nullable()->after('cc_email')->comment('Default BCC email addresses (JSON array or comma-separated)');
            
            // Reply-to configuration
            $table->string('reply_to_email', 255)->nullable()->after('bcc_email')->comment('Reply-to email address for this template version');
            $table->string('reply_to_name', 255)->nullable()->after('reply_to_email')->comment('Reply-to name for this template version');
            
            // Add indexes for performance on frequently queried fields
            $table->index('from_email', 'idx_email_template_versions_from_email');
            $table->index('reply_to_email', 'idx_email_template_versions_reply_to_email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('email_template_versions', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_email_template_versions_from_email');
            $table->dropIndex('idx_email_template_versions_reply_to_email');
            
            // Drop columns in reverse order
            $table->dropColumn([
                'reply_to_name',
                'reply_to_email',
                'bcc_email',
                'cc_email',
                'to_email',
                'from_name',
                'from_email',
            ]);
        });
    }
};