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
        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->onDelete('cascade');
            $table->unsignedInteger('version')->default(1);
            $table->string('subject');
            $table->longText('html_content');
            $table->longText('text_content')->nullable();
            $table->json('placeholders')->nullable()->comment('Available placeholders for this template version');
            $table->boolean('is_active')->default(false)->comment('Indicates if this version is the currently active one');
            $table->timestamps();

            $table->unique(['email_template_id', 'version']);
            // Ensure only one active version per template
            $table->index(['email_template_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_template_versions');
    }
};