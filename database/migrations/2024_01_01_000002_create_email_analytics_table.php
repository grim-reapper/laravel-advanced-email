<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('email_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('template_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->integer('click_count')->default(0);
            $table->json('click_data')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_analytics');
    }
};